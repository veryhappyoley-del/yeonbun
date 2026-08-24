<?php

namespace App\Services;

use App\Models\ReportChapter;
use App\ReportTypes\ChapterSpec;
use App\Services\Concerns\ExtractsJsonFromAiResponse;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 챕터형(schema_version=2) 리포트의 챕터 1개 스코프 로직: 요청 페이로드를 만들고,
 * 응답 하나를 파싱/검증해서 report_chapters 행에 저장합니다.
 *
 * 실제 HTTP 호출(Http::pool())은 이 클래스가 하지 않고 App\Jobs\GenerateReportJob이
 * 합니다 — 이 클래스는 풀 안에서 여러 챕터를 동시에 다루기 쉽도록 순수 로직만 담당해요.
 * 기존 ReportGenerator(리포트 전체를 한 번에 생성하던 레거시 경로)와 패턴은 동일하되
 * (JSON 추출은 ExtractsJsonFromAiResponse trait으로 공유), 스코프가 "리포트 전체"에서
 * "챕터 하나"로 줄어든 게 핵심 차이입니다 — 그래서 스키마/프롬프트/max_tokens이
 * 모두 훨씬 작고, 챕터 하나가 실패해도 status=failed로만 기록되고 예외를 던지지
 * 않아 나머지 챕터에 영향을 주지 않습니다.
 */
class ChapterGenerator
{
    use ExtractsJsonFromAiResponse;

    // 챕터 하나는 전체 input이 아니라 ChapterSpec::inputKeys로 필터링된 일부만
    // 필요하므로, 레거시 ReportGenerator::MAX_INPUT_JSON_LENGTH(4000)보다 작게 잡음.
    private const MAX_INPUT_JSON_LENGTH = 3000;

    /**
     * Http::pool()의 각 요청에 그대로 넘길 Anthropic Messages API 요청 바디.
     */
    public function requestPayload(ChapterSpec $chapter, array $input): array
    {
        return [
            'model' => config('services.anthropic.model'),
            'max_tokens' => $chapter->maxTokens,
            'messages' => [['role' => 'user', 'content' => $this->prompt($chapter, $input)]],
        ];
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
        $schema = json_encode($chapter->schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "당신은 사주 명리학과 연애 상담에 모두 능숙한 '결'의 코치입니다. ".
            "지금 작성하는 것은 유료 리포트 전체가 아니라, 그 안의 한 챕터('{$chapter->title}')입니다. ".
            "다른 챕터와 내용이 겹치지 않도록 이 챕터의 주제에만 집중하세요.\n\n".
            "--- 이 챕터에 필요한 사주 데이터 ---\n{$json}\n\n".
            "--- 반드시 이 스키마와 100% 동일한 키 구조의 JSON만 출력하세요(코드펜스·설명 문구·마크다운 절대 금지, 값만 채워서) ---\n{$schema}\n\n".
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

        $text = trim(collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode(''));

        if ($text === '') {
            $row->update([
                'status' => 'failed',
                'stop_reason' => $stopReason,
                'output_tokens' => $outputTokens,
                'last_error' => 'empty_response',
            ]);

            return;
        }

        $jsonText = $this->extractJson($text);
        $decoded = $jsonText !== null ? json_decode($jsonText, true) : null;
        $expectedKeys = array_keys($chapter->schema);
        $missingKeys = is_array($decoded) ? array_diff($expectedKeys, array_keys($decoded)) : $expectedKeys;

        if (! empty($missingKeys)) {
            $row->update([
                'status' => 'failed',
                'stop_reason' => $stopReason,
                'output_tokens' => $outputTokens,
                'last_error' => $stopReason === 'max_tokens' ? 'max_tokens_truncated' : 'schema_mismatch',
            ]);

            Log::warning('결 챕터 리포트: JSON 스키마 검증 실패', [
                'report_chapter_id' => $row->id,
                'chapter_key' => $row->chapter_key,
                'stop_reason' => $stopReason,
                'missing_keys' => array_values($missingKeys),
                'text_tail' => mb_substr($text, -300),
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
}
