<?php

/**
 * 유료 리포트 주문 생성(ReportController::checkout) 회귀 테스트.
 *
 * 2026-09-28 코드리뷰에서 (1) 프런트에 중복 클릭 방지가 없었고 (2) 가격이 JS에
 * 하드코딩돼 서버 값과 이중 관리되던 문제를 고쳤다. 둘 다 "고쳐도 다시 깨지기 쉬운"
 * 종류라 서버 쪽 계약을 테스트로 잠가 둔다.
 */

use App\Models\Report;
use App\Models\User;
use App\ReportTypes\ReportTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.toss.client_key', 'test_ck');
    config()->set('services.toss.secret_key', 'test_sk');
});

function reportCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'love_fortune',
        'input' => ['pillars' => ['year' => ['stem' => '을']], 'dayElement' => '목'],
        'title' => '올리님의 나의 연애 나침반',
    ], $overrides);
}

it('같은 입력으로 연달아 주문하면 주문이 하나만 생긴다', function () {
    $user = User::factory()->create();
    $payload = reportCheckoutPayload();

    $first = $this->actingAs($user)->postJson(route('reports.checkout'), $payload)->assertOk();
    $second = $this->actingAs($user)->postJson(route('reports.checkout'), $payload)->assertOk();

    expect(Report::count())->toBe(1);
    expect($second->json('order_id'))->toBe($first->json('order_id'));
});

it('입력이 다르면 별도 주문이 생긴다', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('reports.checkout'), reportCheckoutPayload())->assertOk();
    $this->actingAs($user)->postJson(route('reports.checkout'), reportCheckoutPayload([
        'input' => ['pillars' => ['year' => ['stem' => '갑']], 'dayElement' => '화'],
    ]))->assertOk();

    expect(Report::count())->toBe(2);
});

it('다른 사람의 pending 주문을 재사용하지 않는다', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $payload = reportCheckoutPayload();

    $this->actingAs($a)->postJson(route('reports.checkout'), $payload)->assertOk();
    $this->actingAs($b)->postJson(route('reports.checkout'), $payload)->assertOk();

    expect(Report::count())->toBe(2);
    expect(Report::where('user_id', $a->id)->count())->toBe(1);
    expect(Report::where('user_id', $b->id)->count())->toBe(1);
});

it('결제 금액은 언제나 서버의 ReportType::$price를 따른다', function () {
    $user = User::factory()->create();

    foreach (ReportTypeRegistry::all() as $key => $type) {
        $res = $this->actingAs($user)
            ->postJson(route('reports.checkout'), reportCheckoutPayload([
                'type' => $key,
                'input' => ['marker' => $key], // 타입마다 입력을 다르게 해서 중복 재사용을 피한다
            ]))
            ->assertOk();

        expect($res->json('amount'))->toBe($type->price);
        expect(Report::where('type', $key)->value('amount'))->toBe($type->price);
    }
});

it('레지스트리에 없는 타입은 거부한다', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('reports.checkout'), reportCheckoutPayload(['type' => 'not_a_real_type']))
        ->assertStatus(422);

    expect(Report::count())->toBe(0);
});

it('비로그인 사용자는 주문을 만들 수 없다', function () {
    $this->postJson(route('reports.checkout'), reportCheckoutPayload())->assertUnauthorized();

    expect(Report::count())->toBe(0);
});
