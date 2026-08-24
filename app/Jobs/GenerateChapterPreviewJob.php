<?php

namespace App\Jobs;

use App\Models\ChapterPreview;
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
 * 결제 전 "무료 미리보기" 챕터 하나를 생성합니다(App\Models\ChapterPreview 1행).
 * GenerateReportChapterJob(결제 후 챕터 재시도)과 거의 같은 모양이지만, 이 job은 Report/
 * 결제와 전혀 무관합니다 — App\Http\Controllers\ChapterPreviewController가 로그인 여부와
 * 상관없이(무료 궁합 결과 화면은 비로그인도 볼 수 있음) dispatch합니다.
 *
 * 결제로 이어지면 App\Jobs\GenerateReportJob이 이 결과(같은 input_hash로 찾은 ready 행)를
 * 그대로 복사해서 재사용하고, 그 챕터는 API를 다시 부르지 않습니다.
 */
class GenerateChapterPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // 챕터 하나의 Http 타임아웃(90초)보다 넉넉하게. 미리보기는 사용자가 화면에서 폴링하며
    // 기다리는 중이라, 결제 후 재시도(120초)보다 살짝 더 짧게 잡아 오래 기다리게 하지 않는다.
    public int $timeout = 100;

    public function __construct(public ChapterPreview $preview)
    {
    }

    public function handle(ChapterGenerator $generator): void
    {
        $row = $this->preview->fresh();

        if (! $row || $row->isReady()) {
            return;
        }

        $type = ReportTypeRegistry::get($row->report_type);
        $chapterSpec = $type?->findChapter($row->chapter_key);

        // 등록되지 않은 타입/챕터거나(정의가 그새 바뀌었거나), 이 타입이 애초에 이 챕터를
        // 무료 미리보기로 허용하지 않으면(freePreviewChapterKey 불일치) 조용히 중단 — 컨트롤러
        // 단에서 이미 막지만, 큐에 넘어온 뒤 정의가 바뀌었을 가능성까지 방어.
        if (! $chapterSpec || $type->freePreviewChapterKey !== $row->chapter_key) {
            return;
        }

        $lock = Cache::lock('chapter-preview-generate:'.$row->id, 120);

        if (! $lock->get()) {
            return;
        }

        try {
            $row->update(['status' => 'generating']);

            $payload = $generator->requestPayload($chapterSpec, $row->input ?? []);

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(90)->post('https://api.anthropic.com/v1/messages', $payload);

            $generator->savePreviewResponse($row, $chapterSpec, $response);
        } catch (Throwable $e) {
            $generator->savePreviewResponse($row, $chapterSpec, $e);
        } finally {
            $lock->release();
        }
    }
}
