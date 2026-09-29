<?php

/**
 * 리포트가 끝내 만들어지지 않았을 때의 구제 경로 회귀 테스트.
 *
 * "다시 시도" 버튼만 있고 그다음이 없으면, 결제한 사용자가 막다른 길에 갇힌다.
 * 실패 상태 화면에 문의·환불 경로가 반드시 함께 나오도록 잠가 둔다.
 */

use App\Models\Report;
use App\Models\ReportChapter;
use App\Models\User;
use App\ReportTypes\ReportTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function paidChapteredReport(User $user): Report
{
    return Report::create([
        'user_id' => $user->id,
        'type' => 'love_fortune',
        'schema_version' => 2,
        'order_id' => 'order_'.uniqid(),
        'amount' => 9900,
        'status' => 'paid',
        'title' => '테스트 리포트',
        'input' => ['marker' => 'x'],
    ]);
}

it('일부 챕터가 끝내 실패하면 문의·환불 경로를 함께 보여준다', function () {
    $user = User::factory()->create();
    $report = paidChapteredReport($user);
    $type = ReportTypeRegistry::get('love_fortune');

    // 한 챕터만 실패, 나머지는 완료 — 생성은 끝났지만 일부가 비어 있는 상태.
    foreach ($type->chapters as $i => $chapter) {
        ReportChapter::create([
            'report_id' => $report->id,
            'chapter_key' => $chapter->key,
            'sort_order' => $i,
            'title' => $chapter->title,
            'status' => $i === 0 ? 'failed' : 'ready',
            'content' => $i === 0 ? null : json_encode(['blocks' => []]),
        ]);
    }

    $html = $this->actingAs($user)->get(route('reports.show', $report))->assertOk()->getContent();

    expect($html)->toContain('만들지 못했어요');
    expect($html)->toContain('문의·환불 요청하기');
    // 문의 메일에 주문번호가 미리 채워져 있어야 식별이 된다.
    expect($html)->toContain(rawurlencode($report->order_id));
});

it('모든 챕터가 정상이면 실패 안내를 보여주지 않는다', function () {
    $user = User::factory()->create();
    $report = paidChapteredReport($user);
    $type = ReportTypeRegistry::get('love_fortune');

    foreach ($type->chapters as $i => $chapter) {
        ReportChapter::create([
            'report_id' => $report->id,
            'chapter_key' => $chapter->key,
            'sort_order' => $i,
            'title' => $chapter->title,
            'status' => 'ready',
            'content' => json_encode(['blocks' => []]),
        ]);
    }

    $html = $this->actingAs($user)->get(route('reports.show', $report))->assertOk()->getContent();

    expect($html)->not->toContain('만들지 못했어요');
});

it('생성 대기 화면에도 막혔을 때 쓸 재시도·문의 경로가 들어 있다', function () {
    $user = User::factory()->create();
    $report = paidChapteredReport($user);
    $type = ReportTypeRegistry::get('love_fortune');

    // 아직 아무 챕터도 끝나지 않은 상태 → 진행 화면.
    foreach ($type->chapters as $i => $chapter) {
        ReportChapter::create([
            'report_id' => $report->id,
            'chapter_key' => $chapter->key,
            'sort_order' => $i,
            'title' => $chapter->title,
            'status' => 'pending',
        ]);
    }

    $html = $this->actingAs($user)->get(route('reports.show', $report))->assertOk()->getContent();

    // 처음에는 숨겨져 있고, 폴링이 끝까지 실패하면 JS가 드러낸다.
    expect($html)->toContain('id="chapter-progress-stuck"');
    expect($html)->toContain('리포트 다시 생성하기');
    expect($html)->toContain('문의·환불 요청하기');
});
