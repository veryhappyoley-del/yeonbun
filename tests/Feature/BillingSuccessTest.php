<?php

/**
 * 코인 결제 승인(BillingController::success) 회귀 테스트.
 *
 * 돈이 오가는 경로인데 지금까지 자동 검증이 전혀 없었다(출시전 점검리스트 3항).
 * 특히 "금액 조작"과 "이미 처리된 건 재요청(크레딧 중복 지급)"은 눈으로 보기 어렵고
 * 한 번 뚫리면 바로 손해로 이어지는 종류라 최우선으로 잠가 둔다.
 */

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.toss.client_key', 'test_ck');
    config()->set('services.toss.secret_key', 'test_sk');
});

/** 승인 대기 상태의 결제 건 하나를 만든다. */
function billingPendingPayment(User $user, int $amount = 6900, int $credits = 40): Payment
{
    return Payment::create([
        'user_id' => $user->id,
        'plan' => 'medium',
        'order_id' => 'order_'.uniqid(),
        'credits' => $credits,
        'amount' => $amount,
        'status' => 'pending',
    ]);
}

it('토스 승인이 성공하면 결제를 paid로 바꾸고 코인을 정확히 지급한다', function () {
    Http::fake(['api.tosspayments.com/*' => Http::response(['status' => 'DONE'], 200)]);

    $user = User::factory()->create(['credits' => 10]);
    $payment = billingPendingPayment($user);

    $this->actingAs($user)
        ->get(route('billing.success', [
            'paymentKey' => 'pk_test',
            'orderId' => $payment->order_id,
            'amount' => $payment->amount,
        ]))
        ->assertRedirect(route('billing.complete', ['payment' => $payment->id]));

    expect($payment->fresh()->status)->toBe('paid');
    expect($user->fresh()->credits)->toBe(50); // 10 + 40
});

it('클라이언트가 보낸 금액이 주문 금액과 다르면 코인을 지급하지 않는다', function () {
    Http::fake(); // 토스 승인 API는 애초에 호출되면 안 된다

    $user = User::factory()->create(['credits' => 10]);
    $payment = billingPendingPayment($user, amount: 6900);

    $this->actingAs($user)
        ->get(route('billing.success', [
            'paymentKey' => 'pk_test',
            'orderId' => $payment->order_id,
            'amount' => 100, // 조작된 금액
        ]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing_error');

    expect($payment->fresh()->status)->toBe('pending');
    expect($user->fresh()->credits)->toBe(10);
    Http::assertNothingSent();
});

it('이미 승인된 결제를 다시 호출해도 코인이 두 번 지급되지 않는다', function () {
    Http::fake();

    $user = User::factory()->create(['credits' => 50]);
    $payment = billingPendingPayment($user);
    $payment->update(['status' => 'paid']);

    $this->actingAs($user)
        ->get(route('billing.success', [
            'paymentKey' => 'pk_test',
            'orderId' => $payment->order_id,
            'amount' => $payment->amount,
        ]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing_error');

    expect($user->fresh()->credits)->toBe(50);
    Http::assertNothingSent();
});

it('다른 사람의 주문번호로는 승인할 수 없다', function () {
    Http::fake();

    $owner = User::factory()->create(['credits' => 0]);
    $attacker = User::factory()->create(['credits' => 0]);
    $payment = billingPendingPayment($owner);

    $this->actingAs($attacker)
        ->get(route('billing.success', [
            'paymentKey' => 'pk_test',
            'orderId' => $payment->order_id,
            'amount' => $payment->amount,
        ]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing_error');

    expect($payment->fresh()->status)->toBe('pending');
    expect($attacker->fresh()->credits)->toBe(0);
    expect($owner->fresh()->credits)->toBe(0);
});

it('토스 승인이 실패하면 failed로 바꾸고 코인을 지급하지 않는다', function () {
    Http::fake(['api.tosspayments.com/*' => Http::response(['message' => '카드 한도 초과'], 400)]);

    $user = User::factory()->create(['credits' => 10]);
    $payment = billingPendingPayment($user);

    $this->actingAs($user)
        ->get(route('billing.success', [
            'paymentKey' => 'pk_test',
            'orderId' => $payment->order_id,
            'amount' => $payment->amount,
        ]))
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('billing_error');

    expect($payment->fresh()->status)->toBe('failed');
    expect($user->fresh()->credits)->toBe(10);
});

it('결제 완료 화면은 본인의 paid 건만 볼 수 있다', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $payment = billingPendingPayment($owner);

    // 아직 pending
    $this->actingAs($owner)->get(route('billing.complete', ['payment' => $payment->id]))->assertNotFound();

    $payment->update(['status' => 'paid']);
    $this->actingAs($owner)->get(route('billing.complete', ['payment' => $payment->id]))->assertOk();
    $this->actingAs($other)->get(route('billing.complete', ['payment' => $payment->id]))->assertNotFound();
});
