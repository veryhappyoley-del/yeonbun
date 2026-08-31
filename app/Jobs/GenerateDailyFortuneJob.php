<?php

namespace App\Jobs;

use App\Models\DailyFortune;
use App\Services\DailyFortuneGenerator;
use App\Services\DayPillarCalculator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * $dailyFortune 행 하나를 채운다(GenerateReportChapterJob과 같은 패턴 — 잠금으로
 * 중복 실행 방지, 예외를 절대 밖으로 던지지 않고 status=failed로만 기록).
 * App\Console\Commands\GenerateDailyFortunes가 구독자 수만큼 이 job을 큐에 넣는다.
 *
 * 생성이 끝나면(성공/실패 무관하게 성공한 경우에만) SendDailyFortuneEmailJob을 체이닝한다.
 */
class GenerateDailyFortuneJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public DailyFortune $dailyFortune)
    {
    }

    public function handle(DailyFortuneGenerator $generator, DayPillarCalculator $calculator): void
    {
        $row = $this->dailyFortune->fresh();

        if (! $row || $row->isReady()) {
            return;
        }

        $lock = Cache::lock('daily-fortune-generate:'.$row->id, 90);

        if (! $lock->get()) {
            return;
        }

        try {
            $user = $row->user;
            $profile = $user?->sajuProfile;

            if (! $profile) {
                $row->update(['status' => 'failed', 'last_error' => 'no_saju_profile']);

                return;
            }

            $row->update(['status' => 'generating']);

            $myDayPillar = $calculator->pillarForBirth(
                (int) $profile->birth_date->format('Y'),
                (int) $profile->birth_date->format('n'),
                (int) $profile->birth_date->format('j'),
                $profile->birth_time_unknown ? null : $profile->birth_hour,
                $profile->birth_time_unknown ? null : $profile->birth_minute,
                $profile->longitude,
            );

            $todayPillar = $calculator->todayPillar();
            $relation = $calculator->relationOf($myDayPillar['stemElement'], $todayPillar['stemElement']);

            $facts = [
                'name' => $profile->name,
                'gender' => $profile->gender,
                'myDayPillar' => $myDayPillar,
                'todayPillar' => $todayPillar,
                'relation' => $relation,
            ];

            $payload = $generator->requestPayload($facts);

            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(45)->post('https://api.anthropic.com/v1/messages', $payload);

            $generator->saveResponse($row, $response);

            if ($row->fresh()->isReady()) {
                SendDailyFortuneEmailJob::dispatch($row->fresh());
            }
        } catch (Throwable $e) {
            $generator->saveResponse($row, $e);
        } finally {
            $lock->release();
        }
    }
}
