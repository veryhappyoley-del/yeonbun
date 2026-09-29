<?php

/**
 * 비용이 드는 엔드포인트의 요청 제한 회귀 테스트.
 *
 * 크레딧 잔액 검사는 채팅에만 걸리고, 리포트 재생성처럼 크레딧을 쓰지 않으면서
 * 바로 큐에 AI 작업을 올리는 경로는 잔액으로 막히지 않는다. throttle이 빠지면
 * 조용히 비용이 새므로 라우트 설정 자체를 테스트로 잠가 둔다.
 */

use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function middlewareOf(string $routeName): array
{
    $route = Route::getRoutes()->getByName($routeName);
    expect($route)->not->toBeNull();

    return $route->gatherMiddleware();
}

it('돈이 드는 엔드포인트에 throttle이 걸려 있다', function () {
    $expected = [
        'reports.checkout' => 'throttle:20,1',
        'reports.regenerate' => 'throttle:6,1',
        'reports.chapters.regenerate' => 'throttle:30,1',
        'billing.checkout' => 'throttle:20,1',
        'billing.purchase' => 'throttle:20,1',
        'fortune.checkout' => 'throttle:20,1',
    ];

    foreach ($expected as $name => $throttle) {
        expect(middlewareOf($name))->toContain($throttle);
    }
});

it('AI를 호출하는 채팅 엔드포인트에 throttle이 걸려 있다', function () {
    $route = collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->uri() === 'chat/{chatSession}/message');

    expect($route)->not->toBeNull();
    expect($route->gatherMiddleware())->toContain('throttle:20,1');
});

it('리포트 전체 재생성은 분당 6회를 넘으면 429를 돌려준다', function () {
    Bus::fake(); // 실제 AI 작업이 큐에 올라가지 않게 한다

    $user = User::factory()->create();
    $report = Report::create([
        'user_id' => $user->id,
        'type' => 'love_fortune',
        'schema_version' => 2,
        'order_id' => 'order_'.uniqid(),
        'amount' => 9900,
        'status' => 'paid',
        'input' => ['marker' => 'x'],
    ]);

    for ($i = 0; $i < 6; $i++) {
        $this->actingAs($user)->post(route('reports.regenerate', $report))->assertRedirect();
    }

    $this->actingAs($user)->post(route('reports.regenerate', $report))->assertStatus(429);
});
