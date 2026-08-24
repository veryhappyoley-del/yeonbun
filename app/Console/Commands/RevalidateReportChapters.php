<?php

namespace App\Console\Commands;

use App\Models\ReportChapter;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use Illuminate\Console\Command;

/**
 * (2026-08-24 신설, 21단계) 운영 배포 직후 `paragraphs.blade.php`에서
 * "foreach() argument must be of type array|object, string given" 에러가 발생한 것을
 * 계기로 만든 일회성/반복 실행 가능 점검 명령입니다.
 *
 * 배경 ①: ChapterGenerator가 Anthropic Tool Use로 전환되기 전(레거시 텍스트-JSON
 * 프롬프트 방식)에는 응답 저장 시 최상위 키가 다 있는지만 확인했고, 그 값의 "모양"
 * (타입)까지는 검증하지 않았습니다. 그래서 예를 들어 paragraphs가 ["문단1", "문단2"]
 * 배열이어야 하는데 "문단 하나짜리 문자열"로 온 응답도 그대로 status=ready로 저장될
 * 수 있었고, 그 챕터를 렌더링하는 순간 Blade 블록 파셜이 크래시했습니다.
 *
 * 배경 ②(시각 블록 확장 라운드에서 추가): 챕터 콘텐츠 품질을 높이면서 일부 챕터의
 * ChapterSpec::$schema 자체가 개정되어 최상위 키 이름이 통째로 바뀌는 경우도 생겼습니다
 * (예: who_attracts_you가 stage_grid용 `stages` 키 대신 compare_cards용 `compare` 키를
 * 쓰도록 바뀜). 이미 결제해서 저장된 레거시 content는 새 키가 아예 없으므로, "키 자체가
 * 없는" 문제도 "키는 있는데 타입이 다른" 문제와 함께 검증해야 합니다.
 *
 * 이 명령은 이미 status=ready로 저장된 report_chapters 행을 전부 훑어서, 그 리포트
 * 타입의 ChapterSpec::$schema와 실제 content가 맞는지
 * ChapterGenerator::checkContent()(missing_keys + type_mismatch_keys, saveResponse()가
 * 신규 응답을 검증할 때 쓰는 것과 동일한 로직)로 재검증합니다. 둘 중 하나라도 걸리면
 * 그 챕터를 status=failed로 되돌립니다 — chapter-reader.blade.php는 failed 챕터에
 * 이미 "다시 생성하기" 버튼을 보여주므로, 사용자는 그 버튼만 누르면 새 Tool Use
 * 경로로 안전하게 재생성됩니다(다시 안 맞으면 이번엔 애초에 ready로 저장되지 않으므로
 * 무한 반복될 수 없습니다).
 *
 * 사용법: php artisan chapters:revalidate       (실제로 failed 처리)
 *         php artisan chapters:revalidate --dry-run  (몇 건이 문제인지만 보고, 변경 없음)
 */
class RevalidateReportChapters extends Command
{
    protected $signature = 'chapters:revalidate {--dry-run : 실제로 상태를 바꾸지 않고 몇 건이 문제인지만 보고합니다}';

    protected $description = '이미 ready로 저장된 챕터형 리포트의 content가 현재 스키마(키/타입)와 맞는지 재검증하고, 안 맞으면 failed로 되돌립니다(재시도 버튼으로 복구 가능).';

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

                    $issues = $generator->checkContent($chapterSpec->schema, $row->content);

                    if (empty($issues['missing_keys']) && empty($issues['type_mismatch_keys'])) {
                        continue;
                    }

                    $flagged++;

                    $this->line(sprintf(
                        '[%s] report=%s chapter=%s missing_keys=%s type_mismatch_keys=%s%s',
                        $dryRun ? 'DRY-RUN' : 'FIXED',
                        $report->id,
                        $row->chapter_key,
                        implode(',', $issues['missing_keys']) ?: '-',
                        implode(',', $issues['type_mismatch_keys']) ?: '-',
                        $dryRun ? '' : ' -> status=failed'
                    ));

                    if (! $dryRun) {
                        $row->update([
                            'status' => 'failed',
                            'last_error' => empty($issues['missing_keys'])
                                ? 'legacy_schema_type_mismatch'
                                : 'legacy_schema_key_removed',
                        ]);
                    }
                }
            });

        $this->newLine();
        $this->info("검사한 챕터: {$checked}건, 문제 발견: {$flagged}건".($dryRun ? ' (dry-run — 실제로 바뀐 건 없음)' : ' (failed로 전환됨)'));

        return self::SUCCESS;
    }
}
