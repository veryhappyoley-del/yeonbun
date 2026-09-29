<?php

/**
 * 약관·환불정책·결제 전 동의 회귀 테스트 (2026-09-28 추가분).
 *
 * 결제를 받는 서비스의 법적 요건이라, 리팩터링 중에 조용히 사라지면 곤란하다.
 */

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('이용약관 페이지는 로그인 없이도 열리고 환불 조항을 담고 있다', function () {
    $res = $this->get(route('terms.index'))->assertOk();

    $res->assertSee('청약철회', escape: false);
    $res->assertSee('환불', escape: false);
    $res->assertSee('id="refund"', escape: false); // 결제 화면에서 딥링크로 가리키는 앵커
});

it('개인정보 처리방침도 로그인 없이 열린다', function () {
    $this->get(route('privacy.index'))->assertOk();
});

it('모든 사용자 화면 푸터에서 약관으로 갈 수 있다', function () {
    $this->get(route('home'))->assertOk()->assertSee(route('terms.index'), escape: false);
});

it('코인 충전 화면은 동의 전 결제 버튼이 잠겨 있다', function () {
    $user = User::factory()->create();
    config()->set('services.toss.client_key', 'test_ck');
    config()->set('services.toss.secret_key', 'test_sk');

    $html = $this->actingAs($user)->get(route('billing.index'))->assertOk()->getContent();

    expect($html)->toContain('id="billing-consent"');
    // JS가 로드되지 않아도 결제가 진행되지 않도록 마크업 단계에서부터 잠가 둔다.
    expect($html)->toMatch('/class="btn plan-buy"[^>]*disabled/');
});
