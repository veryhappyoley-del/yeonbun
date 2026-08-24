<?php

namespace App\Jobs;

use App\Models\ReportChapter;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 챕터형(schema_version=2) 리포트에서 챕터 딱 하나만 재시도합니다. 20개 챕터 중
 * 1개만 실패했을 때 GenerateReportJob(전체 오케스트레이터)을 다시 돌리는 대신,
 * 이미 ready인 나머지 19개는 그대로 두고 실패한 챕터만 독립적으로 재생성합니다.
 *
 * ReportController::regenerateChapter()가 이 job을 dispatch합니다.
 */
class GenerateReportChapterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // 챕터 하나의 Http 타임아웃(90초)보다 넉넉하게.
    public int $timeout = 120;

    public function __construct(public ReportChapter $chapter)
    {
    }

    public function handle(ChapterGenerator $generator): void
    {
        $row = $this->chapter->fresh();

        if (! $row || $row->isReady()) {
            return;
        }

        $report = $row->report;

        if (! $report || $report->status !== 'paid') {
            return;
        }

        $type = ReportTypeRegistry::get($report->type);
        $chapterSpec = $type?->findChapter($row->chapter_key);

        if (! $chapterSpec) {
            return;
        }

        // GenerateReportJob(전체 오케스트레이터)이 같은 리포트를 동시에 돌고 있을 수도
        // 있으니, 챕터 단위의 짧은 잠금으로 겹침을 막는다.
        $lock = Cache::lock('report-chapter-generate:'.$row->id, 150);

        if (! $lock->get()) {
            return;
        }

        try {
            $row->update(['status' => 'generating']);

            $payload = $generator->requestPayload($chapterSpec, $report->input ?? []);

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post('https://api.anthropic.com/v1/messages', $payload);

            $generator->saveResponse($row, $chapterSpec, $response);
        } catch (Throwable $e) {
            $generator->saveResponse($row, $chapterSpec, $e);
        } finally {
            $lock->release();
        }
    }
}
