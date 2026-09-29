<?php

/**
 * 유료 리포트 접근 권한 회귀 테스트.
 *
 * 리포트 URL은 추측하기 어려운 UUID지만, 그것만으로 접근 제어를 대신할 수는 없다.
 * "결제한 본인의 paid 건만" 열람 가능하다는 계약을 잠가 둔다.
 */

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeReport(User $user, string $status = 'paid'): Report
{
    return Report::create([
        'user_id' => $user->id,
        'type' => 'love_fortune',
        'schema_version' => 2,
        'order_id' => 'order_'.uniqid(),
        'amount' => 9900,
        'status' => $status,
        'title' => '테스트 리포트',
        'input' => ['marker' => 'x'],
    ]);
}

it('다른 사람의 리포트는 열람할 수 없다', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $report = makeReport($owner);

    $this->actingAs($other)->get(route('reports.show', $report))->assertNotFound();
    $this->actingAs($other)->get(route('reports.status', $report))->assertNotFound();
});

it('결제가 완료되지 않은 리포트는 열람할 수 없다', function () {
    $user = User::factory()->create();
    $report = makeReport($user, status: 'pending');

    $this->actingAs($user)->get(route('reports.show', $report))->assertNotFound();
});

it('본인의 결제 완료 리포트는 열람할 수 있다', function () {
    $user = User::factory()->create();
    $report = makeReport($user);

    $this->actingAs($user)->get(route('reports.show', $report))->assertOk();
});

it('비로그인 사용자는 리포트함과 리포트에 접근할 수 없다', function () {
    $user = User::factory()->create();
    $report = makeReport($user);

    $this->get(route('reports.index'))->assertRedirect();
    $this->get(route('reports.show', $report))->assertRedirect();
});

it('다른 사람의 리포트를 재생성할 수 없다', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $report = makeReport($owner);

    $this->actingAs($other)->post(route('reports.regenerate', $report))->assertNotFound();
});
