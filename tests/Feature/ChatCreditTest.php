<?php

/**
 * AI 연애 코치의 코인 차감 회귀 테스트.
 *
 * "잔액 확인 → AI 호출 → 차감" 순서에는 같은 계정의 동시 요청이 잔액을 두 번 통과하는
 * 경쟁 조건이 있어서, 지금은 AI를 부르기 "전에" 원자적으로 선차감하고 호출이 실패하면
 * 환불하는 구조다(ChatController::sendMessage). 그 순서가 다시 뒤집히지 않도록 잠가 둔다.
 */

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.model', 'claude-test');
    config()->set('services.anthropic.max_tokens', 256);
});

function chatSessionFor(User $user): ChatSession
{
    return ChatSession::create([
        'user_id' => $user->id,
        'name' => '올리',
        'saju_context' => ['dayElement' => '목'],
        'summarized_count' => 0,
    ]);
}

it('AI 응답이 성공하면 코인이 정확히 1개 줄어든다', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => '안녕하세요']],
    ], 200)]);

    $user = User::factory()->create(['credits' => 3]);
    $session = chatSessionFor($user);

    $this->actingAs($user)
        ->postJson("/chat/{$session->id}/message", ['message' => '안녕'])
        ->assertOk();

    expect($user->fresh()->credits)->toBe(2);
});

it('코인이 없으면 AI를 호출하지 않고 402를 돌려준다', function () {
    Http::fake();

    $user = User::factory()->create(['credits' => 0]);
    $session = chatSessionFor($user);

    $this->actingAs($user)
        ->postJson("/chat/{$session->id}/message", ['message' => '안녕'])
        ->assertStatus(402)
        ->assertJson(['needs_payment' => true]);

    expect($user->fresh()->credits)->toBe(0);
    Http::assertNothingSent();
});

it('AI 호출이 실패하면 선차감한 코인을 되돌려준다', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => '과부하']], 529)]);

    $user = User::factory()->create(['credits' => 3]);
    $session = chatSessionFor($user);

    $this->actingAs($user)
        ->postJson("/chat/{$session->id}/message", ['message' => '안녕'])
        ->assertStatus(502);

    expect($user->fresh()->credits)->toBe(3);
});

it('다른 사람의 상담 세션에는 메시지를 보낼 수 없다', function () {
    Http::fake();

    $owner = User::factory()->create(['credits' => 5]);
    $attacker = User::factory()->create(['credits' => 5]);
    $session = chatSessionFor($owner);

    // ChatController::authorizeOwner()는 404가 아니라 403으로 막는다.
    $this->actingAs($attacker)
        ->postJson("/chat/{$session->id}/message", ['message' => '안녕'])
        ->assertForbidden();

    expect($attacker->fresh()->credits)->toBe(5);
    Http::assertNothingSent();
});
