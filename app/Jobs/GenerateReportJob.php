<?php

namespace App\Jobs;

use App\Models\ChapterPreview;
use App\Models\Report;
use App\Models\ReportChapter;
use App\ReportTypes\ReportType;
use App\ReportTypes\ReportTypeRegistry;
use App\Services\ChapterGenerator;
use App\Services\ReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Pool;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "심층 연애 리포트" / "프리미엄 궁합 리포트" 본문을 백그라운드에서 생성합니다.
 *
 * ReportController::success()(결제 승인 직후)와 regenerate()(재시도)가 이 job을
 * dispatch만 하고 바로 리턴합니다 — 실제 Anthropic 호출(길면 1~2분)은 큐 워커가
 * 처리하므로, 웹 요청/게이트웨이 타임아웃과 완전히 무관해집니다.
 *
 * 로컬(Herd)에서 큐가 동작하려면 .env의 QUEUE_CONNECTION=database(기본값)인 상태에서
 * 터미널에 `php artisan queue:work`를 띄워둬야 합니다 — 안 띄워두면 job이 계속
 * "대기 중"으로만 쌓이고 리포트가 영영 생성되지 않으니 꼭 확인해 주세요.
 *
 * schema_version=1(레거시 single/compat)은 기존처럼 ReportGenerator가 리포트 전체를
 * 한 번의 Anthropic 호출로 생성합니다. schema_version=2(챕터형, ReportTypeRegistry에
 * 등록된 새 리포트 타입)는 이 job이 직접 report_chapters를 채우는 오케스트레이터
 * 역할을 합니다 — Bus::batch() 대신 Http::pool()을 쓰는 이유는, 지금 배포가
 * QUEUE_CONNECTION=database + 워커 프로세스 1개 전제라서 여러 워커가 있어야 이득을
 * 보는 batch보다 "한 잡 안에서 여러 HTTP 요청을 동시에 쏘는" pool이 지금 인프라에
 * 더 맞기 때문입니다.
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // 이 job 자체(큐 워커 프로세스)가 허용되는 최대 실행 시간(초). 레거시(schema_version=1)
    // 경로는 ReportGenerator 내부의 Http 타임아웃(single 260초)보다 넉넉하게 잡은 300초.
    // 챕터형(schema_version=2) 경로는 챕터당 Http 타임아웃(90초) × 배치 수를 감안해
    // handle()에서 더 긴 값을 쓸 수 있도록 releaseAfterEachChapterBatch 없이 한 번에
    // 처리하되, 20챕터/동시성 4 기준 최대 5배치 × 90초 ≈ 450초라 여유 있게 500초로 둠.
    public int $timeout = 500;

    public function __construct(public Report $report)
    {
    }

    public function handle(ChapterGenerator $chapterGenerator, ReportGenerator $legacyGenerator): void
    {
        // 큐에 쌓여있는 동안 상태가 바뀌었을 수 있으니(예: 결제 취소) 최신 상태로 다시 확인.
        $report = $this->report->fresh();

        if (! $report || $report->status !== 'paid') {
            return;
        }

        if ($report->isChaptered()) {
            $this->generateChapters($report, $chapterGenerator);

            return;
        }

        $legacyGenerator->generate($report);
    }

    /**
     * schema_version=2 리포트의 챕터들을 채웁니다. GenerateReportChapterJob(단일 챕터
     * 재시도)과 동시에 돌 수 있으니, 같은 report에 대해서는 짧게 잠금을 걸어 겹치지
     * 않게 합니다(ReportGenerator::generate()와 동일한 패턴).
     */
    private function generateChapters(Report $report, ChapterGenerator $generator): void
    {
        $lock = Cache::lock('report-generate:'.$report->id, 480);

        if (! $lock->get()) {
            return;
        }

        try {
            $type = ReportTypeRegistry::get($report->type);

            if (! $type) {
                Log::warning('결 챕터 리포트: 등록되지 않은 타입', ['report_id' => $report->id, 'type' => $report->type]);

                return;
            }

            // 아직 report_chapters 행이 없으면(최초 생성) 정의된 챕터 전체를 pending으로
            // 미리 만들어 둔다 — 진행률 UI가 처음부터 "N개 중 몇 개 완료"를 보여줄 수 있음.
            if ($report->chapters()->count() === 0) {
                foreach ($type->chapters as $index => $chapter) {
                    $report->chapters()->create([
                        'chapter_key' => $chapter->key,
                        'sort_order' => $index,
                        'title' => $chapter->title,
                        'status' => 'pending',
                    ]);
                }
            }

            $concurrency = max(1, (int) config('services.anthropic.chapter_concurrency', 4));
            $input = $report->input ?? [];

            // pending(아직 시도 안 함) + failed(이전 배치/재시도에서 실패)를 한 번에 대상으로
            // 삼는다 — job이 재시도(tries=2)될 때 이미 ready인 챕터는 건너뛰고 실패한
            // 챕터만 다시 시도하게 되어, "전부 다시 생성"이 아니라 "안 된 것만 재시도"가 됨.
            $pending = $report->chapters()->whereIn('status', ['pending', 'failed'])->get();

            // (2026-08-24 추가) freePreviewChapterKey가 지정된 타입(예: 궁합분석의
            // compat_overview)은 결제 전 무료 티저 화면에서 이미 같은 입력으로 생성해 둔
            // App\Models\ChapterPreview가 있을 수 있다 — 있으면 API를 다시 부르지 않고
            // 그 content를 그대로 복사해서 ready로 저장하고, 이 챕터는 아래 Http::pool
            // 배치에서 제외한다(무료 티저와 결제 후 리포트가 100% 같은 내용을 보여줘야
            // 하므로, 새로 생성하면 안 됨 — 사용자가 미리 본 것과 다른 내용이 나오면 이상함).
            $pending = $this->reuseFreePreview($report, $type, $pending, $generator, $input);

            foreach ($pending->chunk($concurrency) as $batch) {
                $rows = $batch->keyBy('chapter_key');
                $rows->each(fn ($row) => $row->update(['status' => 'generating']));

                $responses = Http::pool(function (Pool $pool) use ($rows, $type, $generator, $input) {
                    foreach ($rows as $key => $row) {
                        $chapter = $type->findChapter($key);

                        if (! $chapter) {
                            continue;
                        }

                        // 이전 시도가 max_tokens 때문에 실패한 채로 재시도되는 chapter라면
                        // (whereIn(['pending','failed'])에 걸려 다시 이 배치에 들어온 경우),
                        // 같은 예산을 또 주는 대신 자동으로 올려서 재요청한다 — "재시도해도
                        // 어차피 또 잘리는" 문제를 구조적으로 막기 위함(ChapterGenerator 참고).
                        $payload = $generator->requestPayload($chapter, $input, $generator->effectiveMaxTokens($chapter, $row));

                        // 챕터 하나의 Http 타임아웃은 90초 — 레거시 단일 호출(260초)보다
                        // 훨씬 짧고 안전하다(챕터 스키마가 작아 실제로 그렇게 오래 안 걸림).
                        $pool->as($key)
                            ->withHeaders([
                                'x-api-key' => config('services.anthropic.key'),
                                'anthropic-version' => '2023-06-01',
                                'content-type' => 'application/json',
                            ])
                            ->timeout(90)
                            ->post('https://api.anthropic.com/v1/messages', $payload);
                    }
                });

                foreach ($responses as $key => $response) {
                    $row = $rows->get($key);
                    $chapter = $type->findChapter($key);

                    if (! $row || ! $chapter) {
                        continue;
                    }

                    // 풀 응답이 오는 즉시 그 챕터 행에 바로 저장 — 전부 끝나고 한꺼번에
                    // 저장하지 않는다. job이 이 배치 도중에 죽어도 재시도 시 이미 ready인
                    // 챕터는 건너뛰고, generating으로 멈춰있던 챕터만 다시 pending 취급되어
                    // 재시도된다(위 whereIn(['pending','failed'])는 generating을 포함하지
                    // 않으므로, job이 중간에 죽어 generating으로 남은 행은 다음 재시도에서
                    // 자동으로 다시 집히지 않는다는 점은 알려진 한계 — GenerateReportChapterJob
                    // 의 개별 재시도(ReportController::regenerateChapter)로 사용자가 직접
                    // 복구할 수 있다).
                    $generator->saveResponse($row, $chapter, $response);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * $type->freePreviewChapterKey가 지정돼 있고, 그 챕터가 아직 pending/failed로 이번
     * 배치에 걸려있다면 같은 입력 해시로 만들어진 ready 상태의 App\Models\ChapterPreview가
     * 있는지 찾아서, 있으면 그 content를 그대로 복사해 저장하고 $pending에서 제외합니다.
     * 없으면(무료 티저를 아예 안 봤거나, 아직 생성 중/실패한 경우) 손대지 않고 그대로
     * 반환해서 평소처럼 Http::pool로 새로 생성됩니다.
     *
     * @param  Collection<int, ReportChapter>  $pending
     * @return Collection<int, ReportChapter>
     */
    private function reuseFreePreview(
        Report $report,
        ReportType $type,
        Collection $pending,
        ChapterGenerator $generator,
        array $input,
    ): Collection {
        if (! $type->freePreviewChapterKey) {
            return $pending;
        }

        $row = $pending->firstWhere('chapter_key', $type->freePreviewChapterKey);

        if (! $row) {
            return $pending;
        }

        $chapterSpec = $type->findChapter($type->freePreviewChapterKey);

        if (! $chapterSpec) {
            return $pending;
        }

        $hash = $generator->previewInputHash($chapterSpec, $input);

        $cached = ChapterPreview::query()
            ->where('report_type', $type->key)
            ->where('chapter_key', $chapterSpec->key)
            ->where('input_hash', $hash)
            ->where('status', 'ready')
            ->first();

        if (! $cached) {
            return $pending;
        }

        $row->update([
            'status' => 'ready',
            'content' => $cached->content,
            'stop_reason' => $cached->stop_reason,
            'output_tokens' => $cached->output_tokens,
            'last_error' => null,
        ]);

        Log::info('결 챕터 리포트: 무료 미리보기 캐시를 재사용해 API 호출을 건너뜀', [
            'report_id' => $report->id,
            'chapter_key' => $chapterSpec->key,
            'chapter_preview_id' => $cached->id,
        ]);

        return $pending->reject(fn ($r) => $r->is($row));
    }
}
