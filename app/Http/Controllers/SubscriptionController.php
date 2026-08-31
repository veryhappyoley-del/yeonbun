<?php

namespace App\Http\Controllers;

use App\Models\FortuneSubscription;
use App\Models\SajuProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "오늘의 운세" 구독 — 생년월일시 프로필 입력 → 토스 빌링(자동결제) 카드 등록 →
 * 매달 자동 청구(App\Console\Commands\ChargeFortuneSubscriptions가 실제 청구 담당).
 *
 * BillingController/ReportController와 같은 원칙: 클라이언트가 보내는 값만으로는
 * 절대 구독을 활성화하지 않고, 반드시 서버가 토스 시크릿 키로 직접 API를 호출해
 * 확인한 뒤에만 status=active로 바꾼다.
 */
class SubscriptionController extends Controller
{
    // 가격은 상수 하나로 정의(BillingController::PLANS와 같은 방식) — 나중에 쉽게 조정 가능.
    // 실사용 데이터가 쌓이기 전까지의 제안 값이라 자유롭게 바꿔도 됨.
    public const PRICE = 3900;

    public function index(Request $request): View
    {
        $user = $request->user();

        // 토스 카드 등록(requestBillingAuth)이 실패하면 failUrl(이 라우트)로
        // ?code=...&message=...가 붙어서 돌아온다 — 세션 플래시 메시지와 같은 자리에 노출.
        if ($request->query('message') && ! session('fortune_error')) {
            session()->flash('fortune_error', $request->query('message'));
        }

        return view('fortune.index', [
            'profile' => $user->sajuProfile,
            'subscription' => $user->fortuneSubscription,
            'latestFortune' => $user->dailyFortunes()->where('status', 'ready')->first(),
            'price' => self::PRICE,
            'tossClientKey' => config('services.toss.client_key'),
            'tossConfigured' => (bool) config('services.toss.client_key') && (bool) config('services.toss.secret_key'),
        ]);
    }

    /**
     * 구독 신청 전 생년월일시 프로필 저장(이미 있으면 갱신). 계산기와 달리 이건 딱 한 번
     * 입력해서 서버에 저장해두는 값이라, saju.blade.php의 계산 로직과는 별개다.
     */
    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:50'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            'birth_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'birth_minute' => ['nullable', 'integer', 'min:0', 'max:59'],
            'birth_time_unknown' => ['nullable', 'boolean'],
            'gender' => ['required', 'in:male,female'],
            'sido' => ['nullable', 'string', 'max:20'],
            'sigungu' => ['nullable', 'string', 'max:20'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $unknownTime = (bool) ($data['birth_time_unknown'] ?? false);

        SajuProfile::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => $data['name'] ?? null,
                'birth_date' => sprintf('%04d-%02d-%02d', $data['birth_year'], $data['birth_month'], $data['birth_day']),
                'birth_hour' => $unknownTime ? null : ($data['birth_hour'] ?? null),
                'birth_minute' => $unknownTime ? null : ($data['birth_minute'] ?? null),
                'birth_time_unknown' => $unknownTime,
                'gender' => $data['gender'],
                'sido' => $data['sido'] ?? null,
                'sigungu' => $data['sigungu'] ?? null,
                'longitude' => $data['longitude'] ?? null,
            ],
        );

        return redirect()->route('fortune.index')->with('fortune_success', '사주 정보가 저장됐어요. 이제 구독을 시작할 수 있어요.');
    }

    /**
     * 토스 빌링 카드 등록(requestBillingAuth) 직전, customerKey를 만들어 pending
     * 구독 행을 준비한다. billing.checkout()과 같은 패턴.
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! config('services.toss.client_key')) {
            return response()->json(['error' => '결제 기능이 아직 설정되지 않았어요.'], 422);
        }

        if (! $request->user()->sajuProfile) {
            return response()->json(['error' => '먼저 생년월일시를 입력해 주세요.'], 422);
        }

        $subscription = FortuneSubscription::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'status' => 'pending',
                'toss_customer_key' => 'yeonbun_user_'.$request->user()->id,
                'price' => self::PRICE,
            ],
        );

        return response()->json([
            'customer_key' => $subscription->toss_customer_key,
        ]);
    }

    /**
     * 토스 카드 등록(requestBillingAuth) 성공 후 successUrl로 돌아왔을 때 호출됨.
     * authKey를 서버가 직접 /v1/billing/authorizations/issue로 교환해서 billingKey를
     * 받아야만(클라이언트가 준 값을 그대로 믿지 않음) 구독을 활성화한다. 활성화 직후
     * 첫 달 요금을 바로 청구한다(다음날 스케줄을 기다리지 않음).
     */
    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'authKey' => ['required', 'string'],
            'customerKey' => ['required', 'string'],
        ]);

        $subscription = FortuneSubscription::where('user_id', $request->user()->id)
            ->where('toss_customer_key', $data['customerKey'])
            ->first();

        if (! $subscription) {
            return redirect()->route('fortune.index')->with('fortune_error', '구독 정보를 확인할 수 없어요.');
        }

        $secretKey = config('services.toss.secret_key');

        $issueResponse = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->post('https://api.tosspayments.com/v1/billing/authorizations/issue', [
            'authKey' => $data['authKey'],
            'customerKey' => $data['customerKey'],
        ]);

        if ($issueResponse->failed()) {
            return redirect()->route('fortune.index')->with(
                'fortune_error',
                '카드 등록에 실패했어요: '.($issueResponse->json('message') ?? '알 수 없는 오류'),
            );
        }

        $billingKey = $issueResponse->json('billingKey');

        $subscription->update([
            'status' => 'active',
            'toss_billing_key' => $billingKey,
            'next_billing_date' => now()->toDateString(),
            'failed_attempts' => 0,
        ]);

        // 첫 달은 가입 즉시 청구한다(다음날 새벽 배치를 기다리지 않음).
        $chargeResponse = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->post("https://api.tosspayments.com/v1/billing/{$billingKey}", [
            'customerKey' => $data['customerKey'],
            'orderId' => 'yeonbun_fortune_'.Str::uuid()->toString(),
            'orderName' => '연록 오늘의 운세 구독',
            'amount' => $subscription->price,
        ]);

        if ($chargeResponse->successful()) {
            $subscription->update(['next_billing_date' => now()->addMonthNoOverflow()->toDateString()]);

            return redirect()->route('fortune.index')->with('fortune_success', '구독이 시작됐어요! 내일 새벽부터 오늘의 운세를 받아보실 수 있어요.');
        }

        // 카드 등록은 됐는데 첫 결제가 실패한 경우 — 구독은 active로 남기고 다음날
        // 스케줄(ChargeFortuneSubscriptions)이 next_billing_date=오늘 그대로라 재시도한다.
        return redirect()->route('fortune.index')->with(
            'fortune_error',
            '카드는 등록됐지만 첫 결제가 아직 처리되지 않았어요. 잠시 후 다시 시도할게요.',
        );
    }

    public function cancel(Request $request): RedirectResponse
    {
        $subscription = $request->user()->fortuneSubscription;

        if ($subscription && $subscription->status !== 'canceled') {
            $subscription->update(['status' => 'canceled', 'canceled_at' => now()]);
        }

        return redirect()->route('fortune.index')->with('fortune_success', '구독이 해지됐어요. 그동안 이용해 주셔서 감사해요.');
    }

    public function today(Request $request): View
    {
        $fortune = $request->user()->dailyFortunes()->where('status', 'ready')->first();

        return view('fortune.today', ['fortune' => $fortune]);
    }
}
