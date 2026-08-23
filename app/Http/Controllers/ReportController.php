<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "심층 개인 리포트"(single) / "프리미엄 궁합 리포트"(compat) 단건 결제 + AI 리포트 생성.
 *
 * 흐름은 BillingController와 거의 동일합니다: checkout()이 pending 리포트를 만들고,
 * 토스 결제창에서 결제가 끝나면 success()가 /v1/payments/confirm으로 승인을 검증한
 * 뒤에만 status를 paid로 바꿉니다. AI 리포트 생성(generateContent)은 결제 승인과
 * 같은 요청에서 하지 않고 reports.show 화면에서 별도 요청(regenerate)으로 트리거합니다
 * — 게이트웨이 타임아웃 이슈 때문에 의도적으로 분리했습니다(success() 주석 참고).
 *
 * single(심층 연애 리포트)은 HTML이 아니라 고정 스키마의 JSON을 content에 저장하고
 * resources/views/reports/partials/single-report.blade.php가 섹션별로 렌더링합니다.
 * compat(프리미엄 궁합 리포트)은 기존처럼 제한된 태그만 쓰는 HTML 조각을 저장합니다.
 *
 * 이 컨트롤러는 사주 계산 로직을 전혀 모릅니다 — 프론트(app.js/reports.js)가 이미
 * 계산해 둔 사주/궁합 요약(JSON, single은 십신·지장간·합충형파해·신강신약·용신 포함)을
 * input으로 받아서 그걸 그대로 프롬프트에 녹일 뿐입니다.
 * 가격은 항상 서버(self::TYPES)가 결정하고, 클라이언트가 보내는 값은 절대 신뢰하지 않습니다.
 */
class ReportController extends Controller
{
    private const TYPES = [
        'single' => [
            'label' => '심층 연애 리포트',
            'price' => 8900,
        ],
        'compat' => [
            'label' => '프리미엄 궁합 리포트',
            'price' => 3900,
        ],
    ];

    // 리포트 본문에 허용하는 태그. Anthropic 응답을 그대로 저장하지 않고
    // 이 목록으로 한 번 걸러서 저장합니다(예상 밖의 태그·스크립트 방지).
    private const ALLOWED_TAGS = '<h3><p><ul><li><strong><em>';

    // 프론트가 보내는 input(JSON)이 지나치게 커지는 걸 막기 위한 안전장치.
    private const MAX_INPUT_JSON_LENGTH = 4000;

    public function index(Request $request): View
    {
        return view('reports.index', [
            'reports' => $request->user()->reports()->where('status', 'paid')->get(),
            'types' => self::TYPES,
        ]);
    }

    /**
     * 결제창을 띄우기 직전, pending 리포트 건을 만들고 주문 정보를 내려줍니다.
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! config('services.toss.client_key')) {
            return response()->json(['error' => '결제 기능이 아직 설정되지 않았어요.'], 422);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            'input' => ['required', 'array'],
            'title' => ['nullable', 'string', 'max:120'],
        ]);

        $type = self::TYPES[$data['type']];

        $report = Report::create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'order_id' => 'gyeol_report_'.Str::uuid()->toString(),
            'amount' => $type['price'],
            'status' => 'pending',
            'title' => $data['title'] ?? null,
            'input' => $data['input'],
        ]);

        return response()->json([
            'order_id' => $report->order_id,
            'amount' => $report->amount,
            'order_name' => "결 {$type['label']}",
            'customer_name' => $request->user()->name,
        ]);
    }

    /**
     * 토스 결제창에서 결제를 마치고 successUrl로 돌아왔을 때 호출됩니다.
     * /v1/payments/confirm 을 서버가 직접 호출해 승인 여부를 검증한 뒤에만
     * status를 paid로 바꾸고, 곧바로 AI 리포트 생성을 시도합니다.
     */
    public function success(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paymentKey' => ['required', 'string'],
            'orderId' => ['required', 'string'],
            'amount' => ['required', 'integer'],
        ]);

        $amount = (int) $data['amount'];

        $report = Report::where('order_id', $data['orderId'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $report || $report->status !== 'pending' || $report->amount !== $amount) {
            return redirect()->route('home')->with('billing_error', '리포트 결제 정보를 확인할 수 없어요. 이미 처리됐거나 금액이 일치하지 않아요.');
        }

        $secretKey = config('services.toss.secret_key');

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->post('https://api.tosspayments.com/v1/payments/confirm', [
            'paymentKey' => $data['paymentKey'],
            'orderId' => $data['orderId'],
            'amount' => $amount,
        ]);

        if ($response->failed()) {
            $report->update(['status' => 'failed']);

            return redirect()->route('home')->with(
                'billing_error',
                '리포트 결제 승인에 실패했어요: '.($response->json('message') ?? '알 수 없는 오류')
            );
        }

        $report->update([
            'status' => 'paid',
            'payment_key' => $data['paymentKey'],
        ]);

        // AI 리포트 생성(수십 초 걸릴 수 있음)은 일부러 여기서 하지 않습니다.
        // 토스 결제 승인 콜백과 같은 요청 안에서 처리하면 게이트웨이/프록시 타임아웃에
        // 걸려 "결제는 됐는데 화면은 에러"인 상황이 생길 수 있어서, 결제 확인이 끝나면
        // 바로 리다이렉트하고 생성은 reports.show 화면에서 별도 요청(regenerate)으로 트리거합니다.
        return redirect()->route('reports.show', $report);
    }

    /**
     * 결제 취소/실패 시 토스가 failUrl로 리다이렉트할 때 호출됩니다.
     */
    public function fail(Request $request): RedirectResponse
    {
        $orderId = $request->query('orderId');

        if ($orderId) {
            Report::where('order_id', $orderId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        $message = $request->query('message', '결제가 취소됐어요.');

        return redirect()->route('home')->with('billing_error', $message);
    }

    /**
     * 결제 완료된 리포트 열람 페이지. 본인 소유 + paid 상태인 리포트만 볼 수 있어요.
     */
    public function show(Request $request, Report $report): View
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        // "심층 연애 리포트"(single)는 content에 JSON을 저장하므로, 뷰에서 다루기 쉽게
        // 여기서 미리 배열로 디코딩해서 넘깁니다. compat(궁합)은 기존처럼 HTML 그대로 씁니다.
        $data = $report->type !== 'compat' ? $this->decodeSingleContent($report) : null;

        return view('reports.show', [
            'report' => $report,
            'type' => self::TYPES[$report->type] ?? null,
            'data' => $data,
        ]);
    }

    /**
     * AI 생성이 실패했거나(또는 이 업데이트 이전의 예전 형식 content라) 콘텐츠를 그대로
     * 쓸 수 없는 리포트를 한 번 더 생성 시도합니다. 이미 결제된 건이라 재결제 없이
     * 생성만 재시도해요.
     */
    public function regenerate(Request $request, Report $report): RedirectResponse
    {
        if ($report->user_id !== $request->user()->id || $report->status !== 'paid') {
            abort(404);
        }

        if (! $this->hasUsableContent($report)) {
            $this->generateContent($report);
        }

        return redirect()->route('reports.show', $report);
    }

    /**
     * content가 "그대로 화면에 쓸 수 있는" 상태인지 판단합니다. compat은 비어있지만
     * 않으면 되고, single은 반드시 singlePrompt()의 스키마를 만족하는 JSON이어야 합니다.
     * (이 검사 덕분에, 이번 업데이트 이전에 저장된 옛 형식의 single 콘텐츠가 있어도
     * "content가 있으니 재생성 안 함" 상태로 멈추지 않고 정상적으로 다시 생성됩니다.)
     */
    private function hasUsableContent(Report $report): bool
    {
        if ($report->type === 'compat') {
            return (bool) $report->content;
        }

        return $this->decodeSingleContent($report) !== null;
    }

    private function decodeSingleContent(Report $report): ?array
    {
        if (! $report->content) {
            return null;
        }

        $decoded = json_decode($report->content, true);

        if (! is_array($decoded) || ! isset($decoded['love_profile'], $decoded['final_verdict'], $decoded['love_os'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * 결제가 확인된 리포트에 대해 Anthropic API를 한 번 호출해서 본문을 생성합니다.
     * 실패해도 이미 결제는 완료된 상태이므로 예외를 여기서 삼키고, content는 null로
     * 남겨둔 채 조용히 리턴합니다 — show() 화면에서 "다시 생성하기" 버튼으로 재시도할 수 있어요.
     */
    private function generateContent(Report $report): void
    {
        if (! config('services.anthropic.key')) {
            return;
        }

        $isSingle = $report->type !== 'compat';

        try {
            $prompt = $isSingle
                ? $this->singlePrompt($report->input ?? [])
                : $this->compatPrompt($report->input ?? []);

            // "심층 연애 리포트"는 스키마가 훨씬 커서(11개 섹션의 JSON) 더 많은 토큰과
            // 더 긴 타임아웃이 필요합니다. 그래도 결제 확인(success())과는 완전히 분리된
            // 별도 요청(regenerate)에서만 호출되므로, 오래 걸려도 결제 자체는 안전합니다.
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($isSingle ? 110 : 60)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model'),
                'max_tokens' => $isSingle ? 4096 : 1200,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                Log::warning('결 리포트 생성 실패', ['report_id' => $report->id, 'status' => $response->status()]);

                return;
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
                $this->saveSingleJsonContent($report, $text);

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
    private function saveSingleJsonContent(Report $report, string $text): void
    {
        $jsonText = $this->extractJson($text);

        if ($jsonText === null) {
            Log::warning('결 심층 리포트: 응답에서 JSON을 찾지 못함', ['report_id' => $report->id]);

            return;
        }

        $decoded = json_decode($jsonText, true);

        if (! is_array($decoded) || ! isset($decoded['love_profile'], $decoded['final_verdict'], $decoded['love_os'])) {
            Log::warning('결 심층 리포트: JSON 스키마 검증 실패', ['report_id' => $report->id]);

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
     * resources/views/reports/show.blade.php가 섹션별로 렌더링한다.
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
    "attraction_stage": { "emotion": "", "thought": "", "behavior": "" },
    "attraction_growth": { "emotion": "", "thought": "", "behavior": "" },
    "relationship_start": { "emotion": "", "thought": "", "behavior": "" },
    "relationship_stage": { "emotion": "", "thought": "", "behavior": "" },
    "conflict_stage": { "emotion": "", "thought": "", "behavior": "" },
    "relationship_crisis": { "emotion": "", "thought": "", "behavior": "" }
  },
  "who_attracts_you": {
    "strongly_attracted": { "description": "", "traits": ["", ""] },
    "short_term_attraction": { "description": "", "traits": ["", ""] },
    "long_term_match": { "description": "", "traits": ["", ""] }
  },
  "love_signal": {
    "early_interest": ["", ""], "growing_interest": ["", ""], "serious_interest": ["", ""],
    "commitment": ["", ""], "warning_signal": ["", ""]
  },
  "strength_weakness": [ { "strength": "", "escalation": "", "weakness": "" } ],
  "recurring_pattern": { "steps": ["", "", "", "", ""], "key_point": "" },
  "partner_view": {
    "first_impression": { "outside": "", "inside": "" },
    "early_relationship": { "outside": "", "inside": "" },
    "middle_relationship": { "outside": "", "inside": "" },
    "conflict": { "outside": "", "inside": "" },
    "deep_relationship": { "outside": "", "inside": "" }
  },
  "compatibility": {
    "scores": {
      "independence": 0, "emotional_expression": 0, "realism": 0, "responsibility": 0,
      "stability": 0, "relationship_leadership": 0, "dependency": 0,
      "emotional_volatility": 0, "communication": 0, "lifestyle_compatibility": 0
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
            "3) love_os/love_signal/partner_view/recurring_pattern은 '사용자가 좋아하면 어떻게 행동하는가, 관계가 시작되면·애매하면·갈등이 생기면·깊어지면·위기가 오면 어떻게 행동하는가'라는 행동 질문에 답하는 방식으로 채우세요.\n".
            "4) strength_weakness는 장점과 약점을 별개 성향이 아니라 '같은 성향이 과도해질 때'의 양면으로 쓰세요(strength → escalation(과도해지면) → weakness 순서로 자연스럽게 이어지게).\n".
            "5) love_score/compatibility.scores는 절대 임의의 숫자가 아니라 사주 데이터(특히 신강신약·십신 분포·오행 균형)에 근거해서 산정하고, note에서 모순돼 보일 수 있는 점수 조합(예: 감정 표현은 낮은데 호감 표현은 높음)이 있다면 이유를 짧게 설명하세요.\n".
            ($hasCharacter
                ? "6) 입력에 무료 캐릭터 카드 유형(characterType)이 있습니다 — character_link에서 이미 보여준 한줄평을 반복하지 말고, '이 유형이 왜 이 사주 요소에서 비롯됐는지 → 실제 행동 → 강해지는 상황 → 장점으로 작용할 때 → 과해졌을 때 문제'를 설명하세요.\n"
                : "6) 입력에 characterType이 없으므로 character_link는 null로 두세요.\n").
            "7) 표현 규칙 — 전문적이면서도 개인적·세련되고 따뜻하며 명확하고 읽기 쉬운 한국어로 쓰고, 명리학 용어는 필요할 때만 쉽게 풀어서 언급하세요.\n".
            "8) 운명론 금지 — '반드시', '절대로', '평생', '무조건', '반드시 헤어진다/결혼한다' 같은 단정적 표현을 쓰지 말고, '~한 경향이 있습니다', '~할 가능성이 있습니다', '~한 상황에서 강하게 나타날 수 있습니다' 같은 표현을 쓰세요.\n".
            "9) 중복 방지 — love_profile/love_os/who_attracts_you/love_signal/strength_weakness/recurring_pattern/partner_view/compatibility/relationship_advice/final_verdict는 서로 다른 질문에 답해야 합니다. 같은 내용을 다른 섹션에서 반복하지 마세요.\n".
            "10) 사주 데이터가 부족하거나(예: 태어난 시간을 몰라 시주가 없음) 앞뒤가 맞지 않으면 confidence를 medium/low로 낮추고, 근거가 약한 부분은 과도하게 구체적으로 단정하지 마세요. 위 JSON의 pillars.day.stemElement와 dayElement가 서로 다르면 data_conflict를 true로 표시하세요(정상적인 경우 거의 항상 false입니다).\n".
            "11) relationship_advice는 반드시 3개 이상, recurring_pattern.steps는 반드시 5개 이상, strength_weakness는 1~2개, final_verdict.love_keywords는 정확히 5개로 채우세요.\n".
            "12) 각 문장형 값은 1~3문장으로 간결하게, love_signal/who_attracts_you.traits 등 리스트 항목은 5~20자 내외의 짧은 문구로 써서 전체 분량이 과도하게 길어지지 않게 하세요.";
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
