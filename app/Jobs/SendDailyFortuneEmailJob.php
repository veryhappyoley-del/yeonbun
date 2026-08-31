<?php

namespace App\Jobs;

use App\Mail\DailyFortuneMail;
use App\Models\DailyFortune;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDailyFortuneEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public DailyFortune $dailyFortune)
    {
    }

    public function handle(): void
    {
        $row = $this->dailyFortune->fresh();

        if (! $row || ! $row->isReady() || $row->sent_at !== null) {
            return;
        }

        $user = $row->user;

        if (! $user?->email || str_ends_with($user->email, '@yeonbun.local')) {
            // 카카오 이메일 동의를 안 한 사용자 — 더미 이메일이라 보낼 곳이 없음.
            // (구독 신청 화면에서 이 경우를 미리 걸러야 하지만, 방어적으로 한 번 더 확인.)
            Log::warning('오늘의 운세 이메일: 발송 가능한 이메일 없음', ['user_id' => $user?->id]);

            return;
        }

        try {
            Mail::to($user->email)->send(new DailyFortuneMail($row));
            $row->update(['sent_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('오늘의 운세 이메일 발송 실패', ['daily_fortune_id' => $row->id, 'message' => $e->getMessage()]);

            throw $e; // 큐 재시도(tries=3)가 처리하도록 다시 던짐.
        }
    }
}
