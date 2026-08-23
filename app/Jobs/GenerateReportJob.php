<?php

namespace App\Jobs;

use App\Models\Report;
use App\Services\ReportGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
 */
class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    // 이 job 자체(큐 워커 프로세스)가 허용되는 최대 실행 시간(초). ReportGenerator 내부의
    // Http 타임아웃(single 170초)보다 넉넉하게 잡아서, API 호출이 끝나기 전에 워커가
    // job을 강제 종료하지 않도록 함.
    public int $timeout = 200;

    public function __construct(public Report $report)
    {
    }

    public function handle(ReportGenerator $generator): void
    {
        // 큐에 쌓여있는 동안 상태가 바뀌었을 수 있으니(예: 결제 취소) 최신 상태로 다시 확인.
        $report = $this->report->fresh();

        if (! $report || $report->status !== 'paid') {
            return;
        }

        $generator->generate($report);
    }
}
