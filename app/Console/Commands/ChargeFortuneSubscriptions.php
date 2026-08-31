<?php

namespace App\Console\Commands;

use App\Models\FortuneSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * 매일 실행되어 "오늘 청구해야 할" 구독(next_billing_date <= 오늘, status=active)을
 * 토스페이먼츠 빌링(자동결제) API로 청구한다.
 *
 * BillingController::success()가 이미 쓰는 원칙(클라이언트 값을 절대 그대로 믿지 않고
 * 서버가 시크릿 키로 직접 API를 호출해 확인)을 그대로 따른다 — 다만 이건 1회성 결제
 * 승인이 아니라 서버가 스스로 시작하는 반복 청구라는 점만 다르다.
 *
 * 실패 처리는 1단계 범위에 맞춰 단순하게: 2회 연속 실패하면 past_due로 전환하고
 * 안내 메일을 시도한다(정교한 재시도 스케줄/쿠폰 등은 이번 범위 밖).
 */
class ChargeFortuneSubscriptions extends Command
{
    protected $signature = 'fortune:charge-subscriptions';

    protected $description = '오늘 청구해야 할 오늘의 운세 구독을 토스 빌링으로 청구합니다.';

    private const MAX_FAILED_ATTEMPTS = 2;

    public function handle(): int
    {
        $secretKey = config('services.toss.secret_key');

        if (! $secretKey) {
            $this->warn('TOSS_SECRET_KEY가 없어서 구독 청구를 건너뜁니다(테스트 환경에서는 정상).');

            return self::SUCCESS;
        }

        $today = now()->toDateString();
        $charged = 0;
        $failed = 0;

        FortuneSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('toss_billing_key')
            ->where('next_billing_date', '<=', $today)
            ->with('user')
            ->chunkById(100, function ($subscriptions) use ($secretKey, &$charged, &$failed) {
                foreach ($subscriptions as $subscription) {
                    $this->chargeOne($subscription, $secretKey) ? $charged++ : $failed++;
                }
            });

        $this->info("청구 완료 {$charged}건, 실패 {$failed}건.");

        return self::SUCCESS;
    }

    private function chargeOne(FortuneSubscription $subscription, string $secretKey): bool
    {
        $orderId = 'yeonbun_fortune_'.Str::uuid()->toString();

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->timeout(30)->post("https://api.tosspayments.com/v1/billing/{$subscription->toss_billing_key}", [
            'customerKey' => $subscription->toss_customer_key,
            'orderId' => $orderId,
            'orderName' => '연록 오늘의 운세 구독',
            'amount' => $subscription->price,
        ]);

        if ($response->successful()) {
            $subscription->update([
                'next_billing_date' => $subscription->next_billing_date->copy()->addMonthNoOverflow(),
                'failed_attempts' => 0,
            ]);

            return true;
        }

        $subscription->increment('failed_attempts');

        Log::warning('오늘의 운세 구독 청구 실패', [
            'subscription_id' => $subscription->id,
            'status' => $response->status(),
            'message' => $response->json('message'),
        ]);

        if ($subscription->failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
            $subscription->update(['status' => 'past_due']);
            $this->notifyPastDue($subscription);
        }

        return false;
    }

    private function notifyPastDue(FortuneSubscription $subscription): void
    {
        $email = $subscription->user?->email;

        if (! $email || str_ends_with($email, '@yeonbun.local')) {
            return;
        }

        try {
            Mail::raw(
                "결제 카드에 문제가 있어 '오늘의 운세' 구독 결제가 계속 실패했어요. ".
                '앱의 마이페이지 > 오늘의 운세 구독 관리에서 카드를 다시 등록해 주세요.',
                function ($message) use ($email) {
                    $message->to($email)->subject('[연록] 오늘의 운세 구독 결제 실패 안내');
                },
            );
        } catch (\Throwable $e) {
            Log::warning('구독 결제 실패 안내 메일 발송 실패', ['message' => $e->getMessage()]);
        }
    }
}
