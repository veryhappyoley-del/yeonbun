<?php

namespace App\Services;

use App\Models\DailyFortune;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * "오늘의 운세" 콘텐츠 생성. app/Services/ChapterGenerator.php가 프리미엄 챕터형
 * 리포트에 쓰는 것과 같은 원칙(Anthropic Tool Use로 스키마를 강제해서, 코드펜스/설명
 * 문구가 섞여 들어오는 문제 자체를 없애고 파싱을 안전하게 만듦)을 그대로 따르되,
 * ReportChapter/ChapterSpec(리포트 전용, 20챕터/블록/미리보기 캐시까지 딸린 무거운
 * 구조)에 억지로 얹지 않고 이 기능만을 위한 훨씬 작은 버전으로 새로 만들었다 —
 * 오늘의 운세는 리포트가 아니라 매일 도는 짧은 배치 콘텐츠라 그 무게가 안 맞는다.
 *
 * **일진(오늘의 干支)과 오행 관계는 AI에게 절대 지어내게 하지 않는다.** 이미
 * DayPillarCalculator(결정론적 계산)가 구한 사실을 프롬프트에 "주어진 사실"로 못
 * 박아 넣고, AI의 역할은 그 사실을 바탕으로 한 짧은 한국어 문구 작성으로만 제한한다
 * — 이 프로젝트가 대운/세운 계산 기능들에서 이미 써온 것과 같은 설계 원칙.
 */
class DailyFortuneGenerator
{
    private const MAX_TOKENS = 900;

    /**
     * 이 스키마와 정확히 같은 모양(키/타입)의 JSON만 받아들인다.
     * (ChapterGenerator::checkContent()의 축소판 — 여기선 챕터 스키마 재검증 시스템
     * 전체를 끌어올 필요가 없어서 이 클래스 안에 작게 다시 둔다.)
     */
    public const SCHEMA = [
        'headline' => '',                 // 오늘 하루를 한 줄로 요약
        'paragraphs' => ['', '', ''],     // 연애운 / 재물·인간관계운 / 오늘 주의할 점, 이 순서 고정
        'lucky_color' => '',
        'lucky_time' => '',
        'keyword' => '',
    ];

    /**
     * $facts: DayPillarCalculator로 구한 결정론적 사실만 담은 배열.
     *   ['name' => ?string, 'gender' => 'male'|'female', 'myDayPillar' => [...], 'todayPillar' => [...], 'relation' => string]
     */
    public function requestPayload(array $facts): array
    {
        return [
            'model' => config('services.anthropic.model'),
            'max_tokens' => self::MAX_TOKENS,
            'tools' => [[
                'name' => 'fill_daily_fortune',
                'description' => '오늘의 운세 콘텐츠를 스키마에 맞춰 채워서 반환합니다.',
                'input_schema' => $this->jsonSchema(),
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => 'fill_daily_fortune'],
            'messages' => [['role' => 'user', 'content' => $this->prompt($facts)]],
        ];
    }

    private function prompt(array $facts): string
    {
        $name = $facts['name'] ?? null;
        $who = $name ? "{$name}님" : '이 사람';
        $relationText = match ($facts['relation']) {
            'generated_by' => '오늘의 기운이 이 사람을 돕는 방향(상생)이에요 — 힘을 보태받는 날.',
            'generates' => '이 사람이 오늘의 기운에 자기 에너지를 쓰는 방향(상생이지만 소모)이에요 — 베풀고 나면 힘이 빠질 수 있는 날.',
            'controls' => '이 사람이 오늘의 기운을 누르는 방향(상극)이에요 — 밀어붙이면 뜻대로 되는 대신 긴장감도 있는 날.',
            'controlled_by' => '오늘의 기운이 이 사람을 누르는 방향(상극)이에요 — 부딪히거나 뜻대로 안 풀릴 수 있는 날, 무리하지 않는 게 안전.',
            'same' => '이 사람의 기운과 오늘의 기운이 같은 결(비화)이에요 — 평소 성향이 강하게 나오는 날.',
            default => '오늘의 기운과 이 사람의 기운 사이 관계가 뚜렷하지 않은 평이한 날이에요.',
        };

        $myPillar = $facts['myDayPillar'];
        $todayPillar = $facts['todayPillar'];

        return "당신은 사주 명리학에 능숙한 '연록'의 오늘의 운세 작가입니다. ".
            "아래는 이미 정확하게 계산된 사실이므로 절대 다른 간지나 오행으로 바꿔 말하지 말고 그대로 활용하세요. ".
            "내용은 반드시 fill_daily_fortune 도구를 호출해서 전달하세요(텍스트로 직접 답하지 마세요).\n\n".
            "--- 계산된 사실 ---\n".
            "대상: {$who} ({$facts['gender']})\n".
            "이 사람의 일간(태어난 날의 천간): {$myPillar['stem']}({$myPillar['stemElement']})\n".
            "오늘({$todayPillar['label']}, {$todayPillar['hanja']})의 천간 오행: {$todayPillar['stemElement']}\n".
            "관계 해석: {$relationText}\n\n".
            "작성 원칙: 이 관계 해석을 연애운/재물·인간관계운/오늘 주의할 점 세 문단에 자연스럽게 녹여내되, ".
            "'상생/상극/비화' 같은 전문 용어를 설명 없이 그대로 쓰지 말고 실제 행동·감정으로 풀어 쓰세요. ".
            "'반드시/절대로' 같은 단정적 표현 대신 '~한 경향이 있어요' 식으로 쓰고, 운명론적 확언은 피하세요. ".
            "각 문단은 2~3문장, 따뜻하고 구체적인 한국어로 쓰세요. headline은 그날의 분위기를 담은 12자 내외 한 문장, ".
            "keyword는 오늘 하루를 한 단어로 압축한 것, lucky_color/lucky_time은 오늘의 오행과 어울리는 색/시간대를 짧게 제안하세요.";
    }

    private function jsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'headline' => ['type' => 'string'],
                'paragraphs' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 3, 'maxItems' => 3],
                'lucky_color' => ['type' => 'string'],
                'lucky_time' => ['type' => 'string'],
                'keyword' => ['type' => 'string'],
            ],
            'required' => ['headline', 'paragraphs', 'lucky_color', 'lucky_time', 'keyword'],
        ];
    }

    /**
     * Http 응답 하나를 파싱/검증해서 DailyFortune 행에 저장한다. ChapterGenerator::
     * saveResponse()와 같은 원칙 — 예외를 절대 밖으로 던지지 않고 status=failed +
     * last_error로만 기록해서, 구독자 한 명의 실패가 그날 배치 전체에 영향을 주지 않는다.
     */
    public function saveResponse(DailyFortune $row, Response|Throwable $response): void
    {
        if ($response instanceof Throwable) {
            $row->update(['status' => 'failed', 'last_error' => $response->getMessage()]);
            Log::warning('오늘의 운세: 요청 예외', ['daily_fortune_id' => $row->id, 'message' => $response->getMessage()]);

            return;
        }

        if ($response->failed()) {
            $row->update(['status' => 'failed', 'last_error' => 'http_'.$response->status()]);
            Log::warning('오늘의 운세: API 실패', ['daily_fortune_id' => $row->id, 'status' => $response->status()]);

            return;
        }

        $toolUse = collect($response->json('content', []))->firstWhere('type', 'tool_use');
        $decoded = is_array($toolUse) ? ($toolUse['input'] ?? null) : null;

        if (! $this->matchesSchema($decoded)) {
            $row->update(['status' => 'failed', 'last_error' => 'schema_mismatch']);
            Log::warning('오늘의 운세: 스키마 검증 실패', ['daily_fortune_id' => $row->id, 'has_tool_use' => $toolUse !== null]);

            return;
        }

        $row->update(['status' => 'ready', 'content' => $decoded, 'last_error' => null]);
    }

    private function matchesSchema(mixed $content): bool
    {
        if (! is_array($content)) {
            return false;
        }

        if (! is_string($content['headline'] ?? null)) {
            return false;
        }

        if (! is_array($content['paragraphs'] ?? null) || count($content['paragraphs']) < 1) {
            return false;
        }

        foreach ($content['paragraphs'] as $p) {
            if (! is_string($p)) {
                return false;
            }
        }

        foreach (['lucky_color', 'lucky_time', 'keyword'] as $key) {
            if (! is_string($content[$key] ?? null)) {
                return false;
            }
        }

        return true;
    }
}
