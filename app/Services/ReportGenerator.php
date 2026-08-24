<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "심층 연애 리포트"(single) / "프리미엄 궁합 리포트"(compat)의 실제 Anthropic API
 * 호출 + 저장 로직.
 *
 * 예전엔 이 로직이 ReportController::generateContent()였고, 결제 승인과는 분리했지만
 * 여전히 웹 요청(reports.show 화면의 regenerate POST) 안에서 동기적으로 실행됐습니다.
 * 리포트 스키마가 커지면서(11개 섹션) 생성이 오래 걸리는 경우 그 요청 자체가 웹서버/
 * 프록시의 타임아웃에 걸리는 문제가 실제로 발생해서, 이번에 큐(App\Jobs\GenerateReportJob)
 * 에서 백그라운드로 실행하도록 분리했습니다 — 큐 워커는 HTTP 요청-응답 시간 제한을
 * 받지 않으므로, 생성이 아무리 오래 걸려도 타임아웃 걱정 없이 끝까지 돌 수 있습니다.
 *
 * ReportController는 이제 이 클래스를 직접 호출하지 않고 GenerateReportJob을 dispatch만
 * 합니다(성공/실패와 무관하게 즉시 리턴). 화면(reports/partials/pending.blade.php)은
 * /reports/{report}/status를 주기적으로 폴링해서 완료 여부를 확인합니다.
 */
class ReportGenerator
{
    private const ALLOWED_TAGS = '<h3><p><ul><li><strong><em>';

    private const MAX_INPUT_JSON_LENGTH = 4000;

    /**
     * 결제가 확인된 리포트에 대해 Anthropic API를 한 번 호출해서 본문을 생성/저장합니다.
     * 실패해도 이미 결제는 완료된 상태이므로 예외를 여기서 삼키고, content는 null로
     * 남겨둔 채 조용히 리턴합니다 — show() 화면에서 "다시 생성하기"로 재시도할 수 있어요.
     */
    public function generate(Report $report): void
    {
        if (! config('services.anthropic.key')) {
            return;
        }

        // 같은 리포트에 대한 생성이 동시에 두 번(예: 결제 직후 자동 dispatch + 사용자의
        // 수동 재시도 더블클릭) 돌지 않도록 짧은 잠금을 건다. 잠금을 못 얻으면 이미 다른
        // 워커가 처리 중이라는 뜻이라 조용히 리턴한다.
        $lock = Cache::lock('report-generate:'.$report->id, 180);

        if (! $lock->get()) {
            return;
        }

        try {
            $this->doGenerate($report);
        } finally {
            $lock->release();
        }
    }

    private function doGenerate(Report $report): void
    {
        $isSingle = $report->type !== 'compat';

        try {
            $prompt = $isSingle
                ? $this->singlePrompt($report->input ?? [])
                : $this->compatPrompt($report->input ?? []);

            // 큐 워커 안에서 실행되므로 웹 요청 타임아웃과 무관합니다 — 다만 Http 클라이언트
            // 자체의 timeout()은 여전히 지정해서 Anthropic 쪽이 응답 없이 무한정 걸리는 걸 막습니다.
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($isSingle ? 260 : 60)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model'),
                // single(심층 연애 리포트)의 max_tokens: 4096(원래) → 8192(1차 수정)로 올렸는데도
                // 실사용에서 여전히 max_tokens에 도달해 응답이 잘렸다(운영 로그로 확인, 1m49s만에
                // 8192 도달) — LOVE OS/PARTNER'S VIEW/LOVE SIGNAL을 4단계로 통합한 뒤에도 여전히
                // 부족했다는 뜻. 실측 생성 속도(약 75 tokens/sec)를 기준으로 여유 있게 14000으로
                // 다시 올리고, 그만큼 Http/Job 타임아웃도 함께 늘렸다(안 늘리면 생성이 끝나기 전에
                // Http 타임아웃이 먼저 걸려서 다른 실패 모드로 바뀔 뿐임). claude-sonnet-5는 최대
                // 128k 출력 토큰을 지원하므로 14000은 여전히 여유 있는 값.
                'max_tokens' => $isSingle ? 14000 : 1200,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                Log::warning('결 리포트 생성 실패', ['report_id' => $report->id, 'status' => $response->status()]);

                return;
            }

            $stopReason = $response->json('stop_reason');
            $outputTokens = $response->json('usage.output_tokens');

            // max_tokens에 도달해서 응답이 잘렸다면, 그 자체가 "왜 저장이 안 됐는지"를
            // 설명해주는 가장 중요한 단서라서 별도로 눈에 띄게 남겨둔다.
            if ($stopReason === 'max_tokens') {
                Log::warning('결 리포트: max_tokens 도달로 응답이 잘렸을 수 있음', [
                    'report_id' => $report->id,
                    'type' => $report->type,
                    'output_tokens' => $outputTokens,
                ]);
            }

            $text = collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode('');

            $text = trim($text);

            if ($text === '') {
                return;
            }

            if ($isSingle) {
                $this->saveSingleJsonContent($report, $text, $stopReason);

                return;
            }

            $report->update([
                'content' => strip_tags($text, self::ALLOWED_TAGS),
            ]);
        } catch (\Throwable $e) {
            Log::warning('결 리포트 생성 예외', ['report_id' => $report->id, 'message' => $e->getMessage()]);
        }
    }

    /**
     * "심층 연애 리포트"는 HTML이 아니라 고정 스키마의 JSON을 저장합니다.
     * 파싱/스키마 검증에 실패하면 저장하지 않고 조용히 리턴합니다(content는 계속 null로
     * 남아 show() 화면의 "다시 생성하기" 버튼으로 재시도할 수 있어요).
     */
    private function saveSingleJsonContent(Report $report, string $text, ?string $stopReason = null): void
    {
        $jsonText = $this->extractJson($text);

        if ($jsonText === null) {
            Log::warning('결 심층 리포트: 응답에서 JSON을 찾지 못함', [
                'report_id' => $report->id,
                'stop_reason' => $stopReason,
                // 실패 원인을 재현 없이 로그만 보고 진단할 수 있도록, 응답의 끝부분을
                // 남겨둔다(끝부분이 곧 "어디서 잘렸는지"를 보여줌).
                'text_tail' => mb_substr($text, -300),
            ]);

            return;
        }

        $decoded = json_decode($jsonText, true);

        if (! is_array($decoded) || ! isset($decoded['love_profile'], $decoded['final_verdict'], $decoded['love_os'])) {
            Log::warning('결 심층 리포트: JSON 스키마 검증 실패', [
                'report_id' => $report->id,
                'stop_reason' => $stopReason,
                'has_love_profile' => is_array($decoded) && isset($decoded['love_profile']),
                'has_love_os' => is_array($decoded) && isset($decoded['love_os']),
                'has_final_verdict' => is_array($decoded) && isset($decoded['final_verdict']),
                'text_tail' => mb_substr($text, -300),
            ]);

            return;
        }

        $report->update([
            'content' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Claude 응답은 프롬프트에서 "JSON만 출력"을 강하게 요구하지만, 혹시 앞뒤에
     * ```json 코드펜스나 설명 문구가 섞여 와도 견고하게 뽑아내기 위한 방어 로직.
     */
    private function extractJson(string $text): ?string
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?/i', '', $text) ?? $text;
        $text = preg_replace('/```\s*$/', '', $text) ?? $text;
        $text = trim($text);

        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        return substr($text, $start, $end - $start + 1);
    }

    private function inputJson(array $input): string
    {
        $json = json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}';

        return mb_substr($json, 0, self::MAX_INPUT_JSON_LENGTH);
    }

    /**
     * "심층 연애 리포트"(single)용 프롬프트. 단순 사주 풀이(성격 나열)가 아니라
     * "사주 데이터 → 명리학적 특징 → 연애 성향 → 행동 패턴 → 반복 패턴 → 장단점 →
     * 상대가 보는 모습 → 궁합 → 실전 조언 → 최종 결론" 순서의 행동 중심 분석을 강제하기
     * 위해, 자유서술 HTML이 아니라 고정 스키마의 JSON 하나만 출력하도록 요구한다.
     * 반환된 JSON은 그대로(재인코딩만 해서) Report.content에 저장되고,
     * resources/views/reports/partials/single-report.blade.php가 섹션별로 렌더링한다.
     *
     * (분량 축소 이력 — 1차) 원래 love_os(6단계)/partner_view(5단계)/love_signal(5개 별도
     * 섹션)이 사실상 같은 "연애 단계별 흐름"을 세 번 다른 각도로 반복하고 있어서, max_tokens를
     * 많이 잡아먹는 주범이었다. love_os·partner_view를 동일한 4단계(attraction/
     * relationship_start/relationship_stage/conflict_crisis)로 합치고, love_signal은
     * 별도 섹션 대신 love_os 각 단계의 signal 필드로 흡수했다. relationship_advice/
     * recurring_pattern.steps도 "N개 이상"(하한만 있어 개수가 들쭉날쭉해질 수 있음)을
     * "정확히 N개"로 못박아서 분량을 예측 가능하게 만들었다.
     *
     * (분량 축소 이력 — 2차) 1차 축소 후에도 운영 로그에서 max_tokens=8192에 계속 도달해서
     * (1m49s만에 소진, final_verdict 없이 잘림) 추가로 손을 봤다: who_attracts_you의
     * long_term_match를 뺐다(compatibility.best_match/ideal_relationship과 내용이 겹침).
     * compatibility.scores에서 love_score와 이름이 겹치던 지표(emotional_expression/
     * relationship_leadership/stability)를 뺐다(10개 → 7개). 그리고 "1~3문장으로 간결하게"
     * 같은 느슨한 지침을 모델이 잘 안 지키는 걸 확인해서, 설명형 필드는 "정확히 1~2문장,
     * 90자 이내"처럼 글자 수 상한을 명시하는 방식으로 더 엄격하게 바꿨다. 그래도 여전히
     * 부족할 경우를 대비해 max_tokens 자체도 8192 → 14000으로 올렸다(Http/Job 타임아웃도
     * 함께 늘림 — doGenerate()의 Http::timeout()과 GenerateReportJob::$timeout 참고).
     */
    private function singlePrompt(array $input): string
    {
        $json = $this->inputJson($input);
        $hasCharacter = ! empty($input['characterType']);

        $schema = <<<'SCHEMA'
{
  "data_conflict": false,
  "confidence": "high|medium|low",
  "saju_basis": { "pillars_note": "", "day_master_note": "", "elemental_note": "" },
  "character_link": null,
  "love_profile": { "type_keywords": ["", "", ""], "summary": "" },
  "love_score": {
    "attraction_expression": 0, "relationship_leadership": 0, "devotion": 0,
    "emotional_expression": 0, "patience": 0, "relationship_cutoff": 0,
    "jealousy": 0, "push_pull_tolerance": 0, "relationship_stability": 0,
    "conflict_resolution": 0, "note": ""
  },
  "love_os": {
    "attraction": { "emotion": "", "thought": "", "behavior": "", "signal": "" },
    "relationship_start": { "emotion": "", "thought": "", "behavior": "", "signal": "" },
    "relationship_stage": { "emotion": "", "thought": "", "behavior": "", "signal": "" },
    "conflict_crisis": { "emotion": "", "thought": "", "behavior": "", "signal": "" }
  },
  "who_attracts_you": {
    "strongly_attracted": { "description": "", "traits": ["", ""] },
    "short_term_attraction": { "description": "", "traits": ["", ""] }
  },
  "strength_weakness": [ { "strength": "", "escalation": "", "weakness": "" } ],
  "recurring_pattern": { "steps": ["", "", "", "", ""], "key_point": "" },
  "partner_view": {
    "attraction": { "outside": "", "inside": "" },
    "relationship_start": { "outside": "", "inside": "" },
    "relationship_stage": { "outside": "", "inside": "" },
    "conflict_crisis": { "outside": "", "inside": "" }
  },
  "compatibility": {
    "scores": {
      "independence": 0, "realism": 0, "responsibility": 0,
      "dependency": 0, "emotional_volatility": 0, "communication": 0, "lifestyle_compatibility": 0
    },
    "best_match": "", "caution_match": "", "ideal_relationship": ""
  },
  "relationship_advice": [ { "situation": "", "problem": "", "action": "" } ],
  "final_verdict": { "statement": "", "love_keywords": ["", "", "", "", ""], "closing_line": "" }
}
SCHEMA;

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 연애 코치입니다. ".
            "아래 사주 계산 결과(JSON, 십신/지장간/합충형파해/신강신약/용신 포함)를 바탕으로, 유료로 판매하는 ".
            "'심층 연애 리포트'를 딱 하나의 JSON 객체로만 작성하세요.\n\n".
            "--- 사주 데이터 ---\n{$json}\n\n".
            "--- 반드시 이 스키마와 100% 동일한 키 구조의 JSON만 출력하세요(코드펜스·설명 문구·마크다운 절대 금지, 값만 채워서) ---\n{$schema}\n\n".
            "핵심 원칙:\n".
            "1) PERSONALIZATION — 모든 문장은 위 사주 데이터(오행/십신/지장간/합충형파해/신강신약/용신 등)와 실제로 연결되어야 합니다. 근거 없이 일반론만 쓰지 마세요.\n".
            "2) BEHAVIORAL INTERPRETATION — '책임감이 강하다', '감정이 풍부하다' 같은 성격 단독 서술은 금지합니다. 반드시 '성격 → 실제 연애 행동 → 상황 → 결과'까지 이어서 쓰세요. ".
            "예) '관계가 애매하게 지속되면 감정을 계속 유지하기보다 관계의 방향을 확인하려는 경향이 있습니다. 상대의 태도가 명확하지 않으면 스스로 상황을 해석하고 결론을 빠르게 내릴 가능성이 있습니다.' 같은 톤으로.\n".
            "3) love_os와 partner_view는 반드시 attraction(호감이 생기고 커질 때)/relationship_start(관계가 시작될 때)/relationship_stage(관계를 이어갈 때)/conflict_crisis(갈등·위기가 올 때)라는 동일한 4단계 키를 쓰고, love_os는 '나의 내면(감정·생각·행동)', partner_view는 '상대가 보는 나(겉으로는·내면에서는)'로 같은 4단계를 서로 다른 관점에서 채우세요. love_os 각 단계의 signal 필드에는 '이 단계에서 상대가 실제로 눈치챌 수 있는 짧은 신호'를 1문장으로 쓰세요(예전엔 이걸 LOVE SIGNAL이라는 별도 섹션으로 뺐었는데, 내용이 love_os와 겹쳐서 이번에 각 단계 안으로 합쳤습니다). recurring_pattern도 같은 행동 질문(관계가 애매하면·갈등이 생기면 실제로 어떻게 행동하는가)에 답하되, 위 4단계와는 다른 각도(반복되는 패턴 자체의 서사)로 쓰세요.\n".
            "4) strength_weakness는 장점과 약점을 별개 성향이 아니라 '같은 성향이 과도해질 때'의 양면으로 쓰세요(strength → escalation(과도해지면) → weakness 순서로 자연스럽게 이어지게).\n".
            "5) love_score/compatibility.scores는 절대 임의의 숫자가 아니라 사주 데이터(특히 신강신약·십신 분포·오행 균형)에 근거해서 산정하고, note에서 모순돼 보일 수 있는 점수 조합(예: 감정 표현은 낮은데 호감 표현은 높음)이 있다면 이유를 짧게 설명하세요. compatibility.scores는 love_score와 겹치는 지표(감정 표현·관계 주도력·안정감 계열)를 일부러 뺐으니 다시 만들어 넣지 마세요 — '나 자신의 연애 성향'은 love_score, '상대와 함께일 때의 궁합'은 compatibility.scores로 역할을 분리하세요.\n".
            ($hasCharacter
                ? "6) 입력에 무료 캐릭터 카드 유형(characterType)이 있습니다 — character_link에서 이미 보여준 한줄평을 반복하지 말고, '이 유형이 왜 이 사주 요소에서 비롯됐는지 → 실제 행동 → 강해지는 상황 → 장점으로 작용할 때 → 과해졌을 때 문제'를 설명하세요.\n"
                : "6) 입력에 characterType이 없으므로 character_link는 null로 두세요.\n").
            "7) 표현 규칙 — 전문적이면서도 개인적·세련되고 따뜻하며 명확하고 읽기 쉬운 한국어로 쓰고, 명리학 용어는 필요할 때만 쉽게 풀어서 언급하세요.\n".
            "8) 운명론 금지 — '반드시', '절대로', '평생', '무조건', '반드시 헤어진다/결혼한다' 같은 단정적 표현을 쓰지 말고, '~한 경향이 있습니다', '~할 가능성이 있습니다', '~한 상황에서 강하게 나타날 수 있습니다' 같은 표현을 쓰세요.\n".
            "9) 중복 방지 — love_profile/love_os/who_attracts_you/strength_weakness/recurring_pattern/partner_view/compatibility/relationship_advice/final_verdict는 서로 다른 질문에 답해야 합니다. 같은 내용을 다른 섹션에서 반복하지 마세요. who_attracts_you는 '어떤 사람에게 강하게/일시적으로 끌리는가'(끌림 그 자체)만 다루고, '장기적으로 잘 맞는 사람'은 compatibility.best_match/ideal_relationship에서 다루세요(예전엔 who_attracts_you에 long_term_match가 따로 있었는데, compatibility와 내용이 겹쳐서 이번에 뺐습니다).\n".
            "10) 사주 데이터가 부족하거나(예: 태어난 시간을 몰라 시주가 없음) 앞뒤가 맞지 않으면 confidence를 medium/low로 낮추고, 근거가 약한 부분은 과도하게 구체적으로 단정하지 마세요. 위 JSON의 pillars.day.stemElement와 dayElement가 서로 다르면 data_conflict를 true로 표시하세요(정상적인 경우 거의 항상 false입니다).\n".
            "11) relationship_advice는 정확히 3개, recurring_pattern.steps는 정확히 5개, strength_weakness는 1~2개, final_verdict.love_keywords는 정확히 5개로 채우세요(더 많이 쓰지 마세요).\n".
            "12) 분량 제한(엄격히 지키세요) — 설명형 문장 값(saju_basis/love_score.note/love_os[*]/who_attracts_you[*].description/partner_view[*]/compatibility.best_match·caution_match·ideal_relationship/relationship_advice[*]/final_verdict.statement 등)은 하나당 정확히 1~2문장, 한국어 기준 90자를 넘기지 마세요. love_os[*].signal은 1문장 40자 이내로 더 짧게 쓰세요. traits/love_keywords 같은 리스트 항목은 5~20자 내외의 짧은 문구로 쓰세요. 3문장 이상 쓰거나 90자를 넘기면 안 됩니다 — 분량보다 밀도 있게 압축된 문장이 더 좋은 결과입니다.";
    }

    private function compatPrompt(array $input): string
    {
        $json = $this->inputJson($input);

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 연애 코치입니다. ".
            "아래는 두 사람의 궁합 계산 결과(JSON)입니다. 이걸 바탕으로 유료로 판매하는 '프리미엄 궁합 리포트'를 작성하세요.\n\n".
            "--- 궁합 데이터 ---\n{$json}\n\n".
            "요구사항:\n".
            "- 출력은 아래 태그만 사용한 HTML 조각으로 작성하세요: <h3>, <p>, <ul>, <li>, <strong>, <em>. 다른 태그·마크다운·설명 문구는 절대 쓰지 마세요.\n".
            "- <h3> 섹션을 4~5개 구성하세요: 궁합 총평, 서로에게 끌리는 지점, 부딪히기 쉬운 지점과 이유, 오래 잘 만나기 위한 조언, 이런 순간엔 특히 조심.\n".
            "- 이미 무료로 보여준 점수와 짧은 풀이(notes)를 그대로 반복하지 말고, 그걸 근거로 훨씬 구체적이고 두 사람 각자의 이름을 자연스럽게 언급하며 풀어주세요.\n".
            "- 전체 분량은 800~1200자 내외의 한국어로 작성하세요. 과장된 확언이나 운명론적 단정은 피하고, 통계적·성향적 해석으로 서술하세요.";
    }
}
