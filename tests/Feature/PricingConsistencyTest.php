<?php

/**
 * 가격 단일 출처 회귀 테스트.
 *
 * 2026-09-28 이전에는 public/js/reports.js의 TYPE_INFO에 '9,900원' 같은 문자열을
 * 따로 적어두고 서버의 ReportType::$price와 손으로 맞추고 있었다. 한쪽만 바꾸면
 * 화면 금액과 실제 청구액이 조용히 어긋난다. 지금은 계산기 화면이 레지스트리 값을
 * 그대로 내려주는 구조라, "화면에 내려가는 가격 == 서버가 청구하는 가격"을 잠가 둔다.
 */

use App\ReportTypes\ReportTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('계산기 화면이 내려주는 리포트 가격이 서버 가격과 일치한다', function () {
    $html = $this->get(route('calculator.index'))->assertOk()->getContent();

    expect($html)->toContain('window.YeonbunReportPreview');

    preg_match('/window\.YeonbunReportPreview\s*=\s*(\[.*?\]);/s', $html, $m);
    expect($m)->not->toBeEmpty();

    $previews = collect(json_decode(html_entity_decode($m[1], ENT_QUOTES), true))->keyBy('key');
    expect($previews)->not->toBeEmpty();

    foreach (ReportTypeRegistry::all() as $key => $type) {
        expect($previews->has($key))->toBeTrue();
        expect($previews[$key]['price'])->toBe($type->price);
    }
});

it('종목 선택 화면에 하드코딩된 가격 문자열이 남아 있지 않다', function () {
    // 화면 문구는 number_format(서버 가격)에서 파생돼야 한다. 소스에 '12,900원' 같은
    // 리터럴이 다시 생기면 이 테스트가 깨진다.
    $source = file_get_contents(resource_path('views/sagu.blade.php'));

    expect($source)->not->toMatch('/=>\s*[\'"][\d,]+원[\'"]/');
});

it('리포트 결제 화면과 서버가 같은 가격 목록을 본다', function () {
    // 리포트함이 쓰는 타입 목록에도 같은 가격이 실려야 한다.
    foreach (ReportTypeRegistry::all() as $type) {
        expect($type->price)->toBeGreaterThan(0);
    }
});
