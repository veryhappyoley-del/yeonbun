<?php

namespace App\Console\Commands;

use App\Models\FortuneSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
 *
 * (보안 점검 대응 — 이중 청구 방지) 이 커맨드가 겹쳐 실행되면(수동 재실행과 스케줄러가
 * 겹치거나, 여러 서버에서 각자 스케줄러가 돌면) 같은 구독이 실제로 두 번 결제될 수
 * 있었다. 세 겹으로 막는다:
 *   1) routes/console.php의 스케줄 등록에 ->withoutOverlapping()->onOneServer() 적용.
 *   2) 이 파일: 구독 행을 lockForUpdate로 잠그고, Toss 호출 "전에" next_billing_date를
 *      먼저 다음 달로 원자적으로 당겨서(예약) 같은 트랜잭션 안에서 커밋 — 그 사이
 *      다른 프로세스가 같은 행을 집어도 이미 조건(status=active, next_billing_date<=오늘)에
 *      안 맞아 걸러진다. 실패 시에는 이 예약을 되돌린다.
 *   3) Toss 청구 요청 자체에 구독+청구월로 결정되는 Idempotency-Key를 실어서, 그래도
 *      같은 청구가 중복 전송되는 극단적인 경우 Toss 쪽에서 최초 응답을 그대로 돌려주게
 *      한다(토스 문서: 같은 멱등키 재요청은 재처리되지 않고 첫 응답과 동일한 응답을 반환).
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
        // 이번에 청구하려는 "청구월"을 먼저 고정해 둡니다 — 아래에서 next_billing_date를
        // 선반영(예약)해버리면 원래 값을 잃어버리므로, orderId/Idempotency-Key와 실패 시
        // 되돌릴 원래 날짜 모두 이 값을 기준으로 계산합니다.
        $billingPeriod = $subscription->next_billing_date->format('Y-m');
        $originalNextBillingDate = $subscription->next_billing_date->copy();

        // 행을 잠그고, "지금도 정말 청구 대상이 맞는지" 다시 확인한 뒤 next_billing_date를
        // 다음 달로 먼저 당겨(예약) 커밋합니다. 외부 API(Toss) 호출은 트랜잭션/잠금 밖에서
        // 하므로(DB 커넥션을 오래 붙잡지 않기 위해), 이 예약이 "같은 구독을 두 번 집어서
        // 처리하는 것"을 막는 실질적인 방어선입니다.
        $reserved = DB::transaction(function () use ($subscription) {
            $fresh = FortuneSubscription::whereKey($subscription->id)->lockForUpdate()->first();

            if (! $fresh
                || $fresh->status !== 'active'
                || ! $fresh->toss_billing_key
                || $fresh->next_billing_date->gt(now()->toDateString())
            ) {
                return null; // 이미 다른 프로세스가 처리했거나, 그 사이 상태가 바뀜
            }

            $fresh->update([
                'next_billing_date' => $fresh->next_billing_date->copy()->addMonthNoOverflow(),
            ]);

            return $fresh;
        });

        if (! $reserved) {
            // "실패"가 아니라 "이미 처리됨"이라 실패 카운트(재시도 알림)에는 넣지 않습니다.
            return true;
        }

        $orderId = "yeonbun_fortune_{$subscription->id}_{$billingPeriod}";
        $idempotencyKey = "fortune-sub-{$subscription->id}-{$billingPeriod}";

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ])->timeout(30)->post("https://api.tosspayments.com/v1/billing/{$subscription->toss_billing_key}", [
            'customerKey' => $subscription->toss_customer_key,
            'orderId' => $orderId,
            'orderName' => '연록 오늘의 운세 구독',
            'amount' => $subscription->price,
        ]);

        if ($response->successful()) {
            $reserved->update(['failed_attempts' => 0]);

            return true;
        }

        // 청구 실패 — 위에서 선반영해 둔 next_billing_date를 원래대로 되돌리고 실패를 기록합니다.
        DB::transaction(function () use ($subscription, $originalNextBillingDate) {
            $fresh = FortuneSubscription::whereKey($subscription->id)->lockForUpdate()->first();

            if (! $fresh) {
                return;
            }

            $fresh->update([
                'next_billing_date' => $originalNextBillingDate,
                'failed_attempts' => $fresh->failed_attempts + 1,
            ]);
        });

        $subscription->refresh();

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
