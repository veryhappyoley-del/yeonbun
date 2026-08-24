<?php

namespace App\Services;

use App\Models\ReportChapter;
use App\ReportTypes\ChapterSpec;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 챕터형(schema_version=2) 리포트의 챕터 1개 스코프 로직: 요청 페이로드를 만들고,
 * 응답 하나를 파싱/검증해서 report_chapters 행에 저장합니다.
 *
 * 실제 HTTP 호출(Http::pool())은 이 클래스가 하지 않고 App\Jobs\GenerateReportJob /
 * GenerateReportChapterJob이 합니다 — 이 클래스는 풀 안에서 여러 챕터를 동시에 다루기
 * 쉽도록 순수 로직만 담당해요.
 *
 * (2026-08-24 개정) 운영 배포 직후 20챕터 중 다수가 max_tokens truncation으로 실패한 걸
 * 계기로, "텍스트로 JSON을 출력해달라고 부탁하는" 방식(레거시 ReportGenerator와 같은 패턴)
 * 대신 **Anthropic Tool Use(강제 함수 호출)**로 전환했습니다. ChapterSpec::schema(예시
 * 배열)를 실제 JSON Schema로 변환해서 tool_choice로 강제하면:
 *   1. 응답이 항상 구조화된 tool_use 블록으로 오므로 코드펜스/설명 문구가 섞여 들어오는
 *      문제 자체가 사라지고(extractJson 같은 방어 로직이 더 이상 필요 없음),
 *   2. "이 스키마와 100% 동일한 JSON만 출력하세요" 같은 형식 지시문이 필요 없어져서
 *      입력 토큰도 절약되고,
 *   3. 모델이 JSON 문법(따옴표/중괄호/코드펜스)에 쓰는 토큰이 줄어드는 만큼 실제 콘텐츠에
 *      더 많은 예산을 쓸 수 있습니다.
 * 그래도 max_tokens truncation 자체가 아예 없어지는 건 아니라서(내용이 정말 길면 여전히
 * 잘릴 수 있음), effectiveMaxTokens()로 "이전 시도가 max_tokens 때문에 실패했다면 재시도
 * 시 예산을 자동으로 올리는" 적응형 재시도도 함께 도입했습니다 — 같은 예산으로 재시도해봐야
 * 다시 잘릴 뿐이라는 점을 구조적으로 해결한 것입니다.
 */
class ChapterGenerator
{
    // 챕터 하나는 전체 input이 아니라 ChapterSpec::inputKeys로 필터링된 일부만
    // 필요하므로, 레거시 ReportGenerator::MAX_INPUT_JSON_LENGTH(4000)보다 작게 잡음.
    private const MAX_INPUT_JSON_LENGTH = 3000;

    // 재시도 시 max_tokens를 올려도 되는 상한(과금 폭주 방지). 어떤 챕터든 이 값을
    // 넘지 않는다 — claude-sonnet-5는 128k 출력 토큰을 지원하므로 8000은 여전히 여유 있음.
    private const MAX_RETRY_TOKENS = 8000;

    /**
     * Http::pool()의 각 요청에 그대로 넘길 Anthropic Messages API 요청 바디.
     * Tool Use로 스키마를 강제하므로 messages에는 "무엇을 채워야 하는지"에 집중한
     * 프롬프트만 담고, "JSON만 출력하라" 같은 형식 지시는 tool_choice가 대신합니다.
     */
    public function requestPayload(ChapterSpec $chapter, array $input, ?int $maxTokensOverride = null): array
    {
        return [
            'model' => config('services.anthropic.model'),
            'max_tokens' => $maxTokensOverride ?? $chapter->maxTokens,
            'tools' => [[
                'name' => 'fill_chapter',
                'description' => "리포트 챕터 '{$chapter->title}'의 내용을 스키마에 맞춰 채워서 반환합니다.",
                'input_schema' => $this->jsonSchemaFor($chapter->schema),
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => 'fill_chapter'],
            'messages' => [['role' => 'user', 'content' => $this->prompt($chapter, $input)]],
        ];
    }

    /**
     * 이전 시도가 max_tokens 도달로 실패했다면, 같은 예산으로 재시도해봐야 다시 잘릴
     * 가능성이 높으므로 시도 횟수에 비례해 예산을 단계적으로 올립니다(1회 실패 후 재시도
     * 1.6배, 2회 실패 후 2.2배 ...), MAX_RETRY_TOKENS로 상한을 둡니다. max_tokens가
     * 아닌 다른 이유(스키마 불일치 등)로 실패했다면 예산을 올려봐야 의미가 없으므로
     * 기본값을 그대로 씁니다.
     */
    public function effectiveMaxTokens(ChapterSpec $chapter, ReportChapter $row): int
    {
        if ($row->stop_reason !== 'max_tokens' || $row->attempts <= 0) {
            return $chapter->maxTokens;
        }

        $multiplier = 1 + (0.6 * $row->attempts);

        return min(self::MAX_RETRY_TOKENS, (int) ceil($chapter->maxTokens * $multiplier));
    }

    /**
     * inputKeys가 지정돼 있으면 그 키만 남기고, 비어있으면 전체 input을 그대로 씁니다.
     * (20개 챕터가 매번 전체 input을 반복 전송하면 입력 토큰이 20배로 뛰는 걸 막기 위함 —
     * ChapterSpec 문서 참고.)
     */
    public function filterInput(ChapterSpec $chapter, array $input): array
    {
        if (empty($chapter->inputKeys)) {
            return $input;
        }

        return array_intersect_key($input, array_flip($chapter->inputKeys));
    }

    private function prompt(ChapterSpec $chapter, array $input): string
    {
        $filtered = $this->filterInput($chapter, $input);
        $json = mb_substr(json_encode($filtered, JSON_UNESCAPED_UNICODE) ?: '{}', 0, self::MAX_INPUT_JSON_LENGTH);

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 코치입니다. ".
            "지금 작성하는 것은 유료 리포트 전체가 아니라, 그 안의 한 챕터('{$chapter->title}')입니다. ".
            "다른 챕터와 내용이 겹치지 않도록 이 챕터의 주제에만 집중하세요. 내용은 반드시 fill_chapter ".
            "도구를 호출해서 전달하세요(텍스트로 직접 답하지 마세요).\n\n".
            "--- 이 챕터에 필요한 사주 데이터 ---\n{$json}\n\n".
            "공통 원칙: 모든 문장은 위 사주 데이터와 실제로 연결되어야 하며, 성격을 나열하지 말고 반드시 '성격 → 실제 행동 → 상황 → 결과'까지 이어서 쓰세요. ".
            "'반드시/절대로/무조건' 같은 단정적 표현 대신 '~한 경향이 있습니다' 식으로 쓰세요. 전문적이면서도 따뜻하고 명확하며 읽기 쉬운 한국어를 쓰세요. ".
            "운명론적 확언은 피하세요.\n\n".
            "이 챕터만의 지침: {$chapter->promptGuidance}";
    }

    /**
     * Http::pool() 응답 하나(성공/실패 Response 또는 커넥션 예외)를 파싱/검증해서
     * report_chapters 행에 저장합니다. 무슨 일이 있어도 예외를 밖으로 던지지 않고
     * status=failed + last_error로만 기록합니다 — 챕터 하나의 실패가 GenerateReportJob
     * 전체나 나머지 챕터에 영향을 주면 안 되기 때문입니다.
     */
    public function saveResponse(ReportChapter $row, ChapterSpec $chapter, Response|Throwable $response): void
    {
        $row->increment('attempts');

        if ($response instanceof Throwable) {
            $row->update(['status' => 'failed', 'last_error' => $response->getMessage()]);

            Log::warning('결 챕터 리포트: 요청 예외', [
                'report_chapter_id' => $row->id,
                'chapter_key' => $row->chapter_key,
                'message' => $response->getMessage(),
            ]);

            return;
        }

        if ($response->failed()) {
            $row->update(['status' => 'failed', 'last_error' => 'http_'.$response->status()]);

            Log::warning('결 챕터 리포트: API 실패', [
                'report_chapter_id' => $row->id,
                'chapter_key' => $row->chapter_key,
                'status' => $response->status(),
            ]);

            return;
        }

        $stopReason = $response->json('stop_reason');
        $outputTokens = $response->json('usage.output_tokens');

        if ($stopReason === 'max_tokens') {
            Log::warning('결 챕터 리포트: max_tokens 도달로 응답이 잘렸을 수 있음', [
                'report_chapter_id' => $row->id,
                'chapter_key' => $row->chapter_key,
                'output_tokens' => $outputTokens,
            ]);
        }

        // Tool Use 응답은 content 배열 안에 {type: "tool_use", name: "fill_chapter", input: {...}}
        // 형태로 오고, input은 이미 파싱된 배열입니다(레거시처럼 텍스트에서 JSON을 오려낼 필요 없음).
        $toolUse = collect($response->json('content', []))->firstWhere('type', 'tool_use');
        $decoded = is_array($toolUse) ? ($toolUse['input'] ?? null) : null;

        $expectedKeys = array_keys($chapter->schema);
        $missingKeys = is_array($decoded) ? array_diff($expectedKeys, array_keys($decoded)) : $expectedKeys;

        if (! is_array($decoded) || ! empty($missingKeys)) {
            $row->update([
                'status' => 'failed',
                'stop_reason' => $stopReason,
                'output_tokens' => $outputTokens,
                'last_error' => $stopReason === 'max_tokens' ? 'max_tokens_truncated' : 'schema_mismatch',
            ]);

            Log::warning('결 챕터 리포트: 도구 호출 스키마 검증 실패', [
                'report_chapter_id' => $row->id,
                'chapter_key' => $row->chapter_key,
                'stop_reason' => $stopReason,
                'has_tool_use' => $toolUse !== null,
                'missing_keys' => array_values($missingKeys),
            ]);

            return;
        }

        $row->update([
            'status' => 'ready',
            'content' => $decoded,
            'stop_reason' => $stopReason,
            'output_tokens' => $outputTokens,
            'last_error' => null,
        ]);
    }

    /**
     * ChapterSpec::schema(예시 값으로 이뤄진 배열)를 Anthropic Tool Use의 input_schema
     * (JSON Schema)로 변환합니다. 챕터 작성자가 여전히 "예시 배열"만 선언하면 되고(기존
     * 작성 방식 그대로), 실제 JSON Schema 변환은 이 메서드가 규칙 기반으로 자동 처리합니다:
     *
     *   - 문자열 예시 → {type: string} (quote_variant처럼 값이 고정 리터럴이면 const로 고정)
     *   - 숫자 예시 → {type: integer} (키 이름이 'value'면 score 필드로 보고 0~100 범위 지정)
     *   - 리스트(순번 키)이고 첫 원소가 스칼라 → {type: array, items: 스칼라 타입, min/maxItems:
     *     예시 배열 길이} — paragraphs/steps처럼 "정확히 N개"가 이미 예시 배열 길이로
     *     정해져 있는 경우.
     *   - 리스트이고 첫 원소가 객체이며 예시 배열 길이가 1 → {type: array, items: 객체 스키마}
     *     (개수 제한 없음) — items(조언/장단점 등)처럼 반복 개수가 promptGuidance의 "정확히
     *     N개" 문구로만 정해지는, 진짜 반복 템플릿인 경우.
     *   - 리스트이고 첫 원소가 객체이며 예시 배열 길이가 2개 이상 → {type: array, items: 객체
     *     스키마, min/maxItems: 예시 배열 길이} — stages.lines처럼 각 자리가 서로 다른
     *     의미(감정/생각/행동/신호)를 갖는 고정 배열인 경우.
     *   - 연관 배열(문자열 키) → {type: object, properties: 재귀 변환, required: 모든 키}
     *     — scores/stages처럼 라벨이 고정된 하위 객체를 강제할 때 사용.
     */
    private function jsonSchemaFor(array $schema): array
    {
        return [
            'type' => 'object',
            'properties' => $this->objectPropertiesFor($schema),
            'required' => array_keys($schema),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function objectPropertiesFor(array $assoc): array
    {
        $properties = [];

        foreach ($assoc as $key => $value) {
            $properties[$key] = $this->schemaForValue($key, $value);
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaForValue(string $key, mixed $value): array
    {
        if (is_string($value)) {
            $schema = ['type' => 'string'];

            if ($key === 'quote_variant' && $value !== '') {
                $schema['const'] = $value;
            }

            return $schema;
        }

        if (is_int($value) || is_float($value)) {
            $schema = ['type' => 'integer'];

            if ($key === 'value') {
                $schema['minimum'] = 0;
                $schema['maximum'] = 100;
            }

            return $schema;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                if (empty($value)) {
                    return ['type' => 'array', 'items' => ['type' => 'string']];
                }

                $first = $value[0];
                $itemSchema = $this->schemaForValue($key, $first);
                $count = count($value);

                $arraySchema = ['type' => 'array', 'items' => $itemSchema];

                // 객체 리스트인데 예시가 딱 1개뿐이면(items 패턴) "정확히 N개"는 promptGuidance
                // 프로즈로만 지정되므로 여기서 개수를 강제하지 않는다. 그 외(스칼라 리스트,
                // 또는 예시가 2개 이상인 객체 리스트=lines 패턴)는 예시 배열 길이를 그대로
                // "정확한 개수"로 강제한다.
                $isSingleObjectTemplate = is_array($first) && ! array_is_list($first) && $count === 1;

                if (! $isSingleObjectTemplate) {
                    $arraySchema['minItems'] = $count;
                    $arraySchema['maxItems'] = $count;
                }

                return $arraySchema;
            }

            return [
                'type' => 'object',
                'properties' => $this->objectPropertiesFor($value),
                'required' => array_keys($value),
            ];
        }

        return ['type' => 'string'];
    }
}
