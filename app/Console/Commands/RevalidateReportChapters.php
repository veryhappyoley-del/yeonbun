<?php

namespace App\Console\Commands;

use App\Models\ReportChapter;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use Illuminate\Console\Command;

/**
 * (2026-08-24 신설) 운영 배포 직후 `paragraphs.blade.php`에서
 * "foreach() argument must be of type array|object, string given" 에러가 발생한 것을
 * 계기로 만든 일회성/반복 실행 가능 점검 명령입니다.
 *
 * 배경: ChapterGenerator가 Anthropic Tool Use로 전환되기 전(레거시 텍스트-JSON 프롬프트
 * 방식)에는 응답 저장 시 최상위 키가 다 있는지만 확인했고, 그 값의 "모양"(타입)까지는
 * 검증하지 않았습니다. 그래서 예를 들어 paragraphs가 ["문단1", "문단2"] 배열이어야
 * 하는데 "문단 하나짜리 문자열"로 온 응답도 그대로 status=ready로 저장될 수 있었고,
 * 그 챕터를 렌더링하는 순간 Blade 블록 파셜이 크래시했습니다.
 *
 * 이 명령은 이미 status=ready로 저장된 report_chapters 행을 전부 훑어서, 그 리포트
 * 타입의 ChapterSpec::$schema와 실제 content의 타입이 맞는지
 * ChapterGenerator::schemaTypeMismatches()로 재검증합니다. 타입이 안 맞으면 그 챕터를
 * status=failed로 되돌립니다 — chapter-reader.blade.php는 failed 챕터에 이미 "다시
 * 생성하기" 버튼을 보여주므로, 사용자는 그 버튼만 누르면 새 Tool Use 경로로 안전하게
 * 재생성됩니다(값 타입이 다시 안 맞으면 이번엔 애초에 ready로 저장되지 않으므로 무한
 * 반복될 수 없습니다).
 *
 * 사용법: php artisan chapters:revalidate       (실제로 failed 처리)
 *         php artisan chapters:revalidate --dry-run  (몇 건이 문제인지만 보고, 변경 없음)
 */
class RevalidateReportChapters extends Command
{
    protected $signature = 'chapters:revalidate {--dry-run : 실제로 상태를 바꾸지 않고 몇 건이 문제인지만 보고합니다}';

    protected $description = '이미 ready로 저장된 챕터형 리포트의 content가 스키마 타입과 맞는지 재검증하고, 안 맞으면 failed로 되돌립니다(재시도 버튼으로 복구 가능).';

    public function handle(ChapterGenerator $generator): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $checked = 0;
        $flagged = 0;

        ReportChapter::query()
            ->where('status', 'ready')
            ->whereNotNull('content')
            ->with('report')
            ->chunkById(100, function ($rows) use ($generator, $dryRun, &$checked, &$flagged) {
                foreach ($rows as $row) {
                    $checked++;

                    $report = $row->report;

                    if (! $report) {
                        continue;
                    }

                    $type = ReportTypeRegistry::get($report->type);
                    $chapterSpec = $type?->findChapter($row->chapter_key);

                    // 정의가 아예 사라진(리포트 타입/챕터 키가 폐기된) 경우는 이 명령의
                    // 대상이 아니므로 건드리지 않고 건너뜁니다.
                    if (! $chapterSpec) {
                        continue;
                    }

                    $content = $row->content;

                    if (! is_array($content)) {
                        $mismatchedKeys = array_keys($chapterSpec->schema);
                    } else {
                        $mismatchedKeys = $generator->schemaTypeMismatches($chapterSpec->schema, $content);
                    }

                    if (empty($mismatchedKeys)) {
                        continue;
                    }

                    $flagged++;

                    $this->line(sprintf(
                        '[%s] report=%s chapter=%s mismatched_keys=%s%s',
                        $dryRun ? 'DRY-RUN' : 'FIXED',
                        $report->id,
                        $row->chapter_key,
                        implode(',', $mismatchedKeys),
                        $dryRun ? '' : ' -> status=failed'
                    ));

                    if (! $dryRun) {
                        $row->update([
                            'status' => 'failed',
                            'last_error' => 'legacy_schema_type_mismatch',
                        ]);
                    }
                }
            });

        $this->newLine();
        $this->info("검사한 챕터: {$checked}건, 문제 발견: {$flagged}건".($dryRun ? ' (dry-run — 실제로 바뀐 건 없음)' : ' (failed로 전환됨)'));

        return self::SUCCESS;
    }
}
