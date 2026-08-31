<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyFortuneJob;
use App\Models\DailyFortune;
use App\Models\FortuneSubscription;
use Illuminate\Console\Command;

/**
 * 매일 새벽(routes/console.php의 스케줄 등록 참고) 활성 구독자 전체의 "오늘의 운세"를
 * 만든다. 이 커맨드 자체는 DB 행만 만들고 실제 AI 호출은 GenerateDailyFortuneJob(큐)이
 * 한다 — report_chapters/GenerateReportJob이 쓰는 것과 같은 "먼저 pending 행을 전부
 * 만들어두고 큐가 채운다" 패턴.
 *
 * 같은 날짜에 이미 daily_fortunes 행이 있으면 건너뛴다(unique(user_id, fortune_date) +
 * firstOrCreate) — 수동으로 다시 실행해도 중복 생성/중복 이메일 발송이 안 된다.
 */
class GenerateDailyFortunes extends Command
{
    protected $signature = 'fortune:generate-daily';

    protected $description = '활성 구독자 전체의 오늘의 운세를 생성합니다(큐에 dispatch).';

    public function handle(): int
    {
        $today = now()->toDateString();
        $count = 0;

        FortuneSubscription::query()
            ->where('status', 'active')
            ->with('user')
            ->chunkById(200, function ($subscriptions) use ($today, &$count) {
                foreach ($subscriptions as $subscription) {
                    if (! $subscription->user) {
                        continue;
                    }

                    $row = DailyFortune::firstOrCreate(
                        ['user_id' => $subscription->user_id, 'fortune_date' => $today],
                        ['status' => 'pending'],
                    );

                    if ($row->status === 'pending') {
                        GenerateDailyFortuneJob::dispatch($row);
                        $count++;
                    }
                }
            });

        $this->info("오늘({$today}) 운세 생성 {$count}건을 큐에 넣었습니다.");

        return self::SUCCESS;
    }
}
