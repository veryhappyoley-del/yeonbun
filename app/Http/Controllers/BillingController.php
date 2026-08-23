<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * "코인(메시지) 충전" 페이지 + 토스페이먼츠 결제창 연동.
 *
 * 흐름 (토스 키가 설정된 경우):
 *   1. 사용자가 플랜을 고르고 "결제하기"를 누르면 checkout()이 Payment(status=pending) 행을 만들고
 *      주문 정보(orderId/amount 등)를 JSON으로 돌려줍니다.
 *   2. 프론트(JS)가 토스 SDK로 결제창을 띄웁니다. 실제 승인은 아직 안 된 상태입니다.
 *   3. 결제 완료 후 토스가 브라우저를 success()로 리다이렉트시키면, 그때 서버가
 *      /v1/payments/confirm API를 시크릿 키로 직접 호출해서 승인 여부를 검증합니다.
 *      이 확인이 끝나야만 credits를 지급합니다 — 클라이언트가 보내는 값만으로는 절대 지급하지 않아요.
 *
 * 토스 키(TOSS_CLIENT_KEY / TOSS_SECRET_KEY)가 .env에 없으면, 위 흐름 대신 purchase()를 통한
 * "로컬 테스트용 즉시 지급"으로 자동 전환됩니다(APP_ENV=local일 때만 동작).
 */
class BillingController extends Controller
{
    /**
     * 코인 플랜. 가격은 예시 값이라 자유롭게 조정하면 됩니다.
     * credits = 지급할 메시지(AI 응답 1회 = 1개) 개수.
     */
    private const PLANS = [
        'small' => [
            'label' => '스몰팩',
            'credits' => 15,
            'price' => 2900,
            'desc' => '가볍게 몇 번만 물어보고 싶을 때',
        ],
        'medium' => [
            'label' => '미디엄팩',
            'credits' => 40,
            'price' => 6900,
            'desc' => '가장 많이 선택하는 구성',
            'highlight' => true,
        ],
        'large' => [
            'label' => '라지팩',
            'credits' => 100,
            'price' => 14900,
            'desc' => '길게 여러 번 상담받고 싶을 때',
        ],
    ];

    public function index(Request $request)
    {
        return view('billing', [
            'plans' => self::PLANS,
            'credits' => $request->user()->credits,
            'isTestMode' => app()->environment('local'),
            'tossClientKey' => config('services.toss.client_key'),
            'tossConfigured' => (bool) config('services.toss.client_key') && (bool) config('services.toss.secret_key'),
        ]);
    }

    /**
     * 결제창을 띄우기 직전, pending 결제 건을 만들고 주문 정보를 내려줍니다.
     * (토스 키가 설정돼 있을 때만 프론트에서 이 엔드포인트를 씁니다.)
     */
    public function checkout(Request $request): JsonResponse
    {
        if (! config('services.toss.client_key')) {
            return response()->json(['error' => '결제 기능이 아직 설정되지 않았어요.'], 422);
        }

        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(self::PLANS))],
        ]);

        $plan = self::PLANS[$data['plan']];

        $payment = Payment::create([
            'user_id' => $request->user()->id,
            'plan' => $data['plan'],
            'order_id' => 'yeonbun_'.Str::uuid()->toString(),
            'credits' => $plan['credits'],
            'amount' => $plan['price'],
            'status' => 'pending',
        ]);

        return response()->json([
            'order_id' => $payment->order_id,
            'amount' => $payment->amount,
            'order_name' => "결 {$plan['label']} ({$plan['credits']}개)",
            'customer_name' => $request->user()->name,
        ]);
    }

    /**
     * 토스 결제창에서 결제를 마치고 successUrl로 돌아왔을 때 호출됩니다.
     * 여기서 /v1/payments/confirm 을 서버가 직접 호출해 승인 여부를 검증한 뒤에만 credits를 줍니다.
     */
    public function success(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paymentKey' => ['required', 'string'],
            'orderId' => ['required', 'string'],
            'amount' => ['required', 'integer'],
        ]);

        // 쿼리스트링으로 들어온 값은 전부 문자열이라, 저장돼 있는 정수 금액과 비교하기 전에 캐스팅합니다.
        $amount = (int) $data['amount'];

        $payment = Payment::where('order_id', $data['orderId'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment || $payment->status !== 'pending' || $payment->amount !== $amount) {
            return redirect()->route('billing.index')->with('billing_error', '결제 정보를 확인할 수 없어요. 이미 처리됐거나 금액이 일치하지 않아요.');
        }

        $secretKey = config('services.toss.secret_key');

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.base64_encode($secretKey.':'),
            'Content-Type' => 'application/json',
        ])->post('https://api.tosspayments.com/v1/payments/confirm', [
            'paymentKey' => $data['paymentKey'],
            'orderId' => $data['orderId'],
            'amount' => $amount,
        ]);

        if ($response->failed()) {
            $payment->update(['status' => 'failed']);

            return redirect()->route('billing.index')->with(
                'billing_error',
                '결제 승인에 실패했어요: '.($response->json('message') ?? '알 수 없는 오류')
            );
        }

        $payment->update([
            'status' => 'paid',
            'payment_key' => $data['paymentKey'],
        ]);

        $request->user()->increment('credits', $payment->credits);

        return redirect()->route('billing.complete', ['payment' => $payment->id]);
    }

    /**
     * 결제 성공 후 도착하는 "결제 완료" 확인 페이지.
     * 본인이 방금 결제한(paid 상태) 건만 볼 수 있게 막아둡니다.
     */
    public function complete(Request $request, Payment $payment)
    {
        if ($payment->user_id !== $request->user()->id || $payment->status !== 'paid') {
            abort(404);
        }

        return view('billing.complete', [
            'payment' => $payment,
            'plan' => self::PLANS[$payment->plan] ?? null,
            'credits' => $request->user()->credits,
        ]);
    }

    /**
     * 결제 취소/실패 시 토스가 failUrl로 리다이렉트할 때 호출됩니다.
     */
    public function fail(Request $request): RedirectResponse
    {
        $orderId = $request->query('orderId');

        if ($orderId) {
            Payment::where('order_id', $orderId)
                ->where('user_id', $request->user()->id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
        }

        $message = $request->query('message', '결제가 취소됐어요.');

        return redirect()->route('billing.index')->with('billing_error', $message);
    }

    /**
     * 토스 키가 아직 없을 때 쓰는 대체 경로. 로컬 환경에서만 동작하는 "가짜 결제"입니다.
     * (운영 환경에서는 실수로라도 무료 충전이 되지 않도록 막아둡니다.)
     */
    public function purchase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(self::PLANS))],
        ]);

        $plan = self::PLANS[$data['plan']];

        if (! app()->environment('local')) {
            return back()->with('billing_error', '결제 기능은 아직 준비 중이에요. 조금만 기다려주세요!');
        }

        $request->user()->increment('credits', $plan['credits']);

        return redirect()->route('home')->with(
            'billing_success',
            "[테스트 모드] {$plan['label']}({$plan['credits']}개)이 충전됐어요. 실제 결제는 아직 연동 전이라 금액은 청구되지 않았어요."
        );
    }
}
