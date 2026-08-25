<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChapterPreviewController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// (2026-08-25 수정) "/"는 이제 진짜 홈 랜딩페이지(home.blade.php)다. 예전엔 계산기 화면이
// 홈을 겸했는데(로드맵 3번 이전), 헤더/하단탭 구조가 자리잡으면서 "홈" 탭이 가리킬 전용
// 마케팅 페이지가 필요해졌다. 명사도(myeongsado.com) 구성을 참고해 히어로+차별점 소개+
// 종목 미리보기 순서로 만들었고, 색/타이포는 전부 결의 종이/먹색/인주색 palette 그대로.
Route::get('/', function () {
    return view('home');
})->middleware('track.view')->name('home');

// (2026-08-25 신설) 계산기 화면(사주 입력 폼+궁합 입력 폼+연애 코치) — 원래 "/"가 겸하던
// 역할을 분리했다. /sagu의 카드나 홈의 CTA가 ?tab=single|compat|chat으로 넘어온다.
Route::get('/calculator', function () {
    return view('saju');
})->middleware('track.view')->name('calculator.index');

// (2026-08-24 신설) 하단 탭바 "사주"의 목적지 — 계산기로 바로 들어가지 않고 종목(나의 연애
// 사주/궁합 보기/연애 코치, 앞으로는 재회전략 등도)을 카테고리별로 먼저 고르는 페이지.
// 로그인 여부와 무관하게 누구나 볼 수 있어야 해서(궁합/연애사주 자체가 로그인 없이도 되는
// 기능이므로) auth 미들웨어 밖에 둔다.
Route::get('/sagu', function () {
    return view('sagu');
})->name('sagu.index');

// (2026-08-24 신설) 하단 탭바 "사전" — 명리학 기초 용어를 쉬운 말로 풀어둔 정적 페이지.
// 리포트 안에 남을 수 있는 전문 용어에 대한 보조 안전장치이자, 검색 유입(SEO)용 공개
// 콘텐츠라 로그인 없이 누구나/검색엔진도 볼 수 있어야 한다.
Route::get('/dictionary', function () {
    return view('dictionary');
})->name('dictionary.index');

// (2026-08-24 신설) 하단 탭바 "마이"/"로그인" — 코인 잔액·충전·리포트함·로그아웃(로그인 시)
// 또는 카카오/네이버 로그인 버튼(비로그인 시)을 한 곳에 모은 페이지. 비로그인 사용자도
// 로그인 버튼을 보려면 이 페이지에 들어와야 하므로 auth 미들웨어 밖에 둔다.
Route::get('/my', function () {
    return view('my');
})->name('my.index');

// 결제 전 "무료 미리보기" 챕터 생성/폴링. 무료 궁합 결과 화면이 로그인 없이도 쓰이므로
// auth 미들웨어 밖에 둡니다 — 대신 throttle로 남용을 막습니다(1분에 20회, 정상적인
// 폴링 패턴엔 충분하고 무한 반복 호출은 막는 수준). ChapterPreviewController가 실제로
// 허용된 (type, chapter) 조합인지 한 번 더 확인합니다.
Route::post('/chapter-previews', [ChapterPreviewController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('chapter-previews.store');

// auth 미들웨어가 비로그인 사용자를 리다이렉트할 때 route('login')을 찾다가
// 에러 나지 않도록 이름만 있는 안전장치용 라우트. 실제 로그인 폼은 없고 마이페이지로 보냄
// (2026-08-25 수정: "/"가 마케팅 홈으로 바뀌면서, 카카오/네이버 로그인 버튼이 바로 보이는
// 마이페이지가 더 정확한 목적지가 됐다).
Route::get('/login', fn () => redirect()->route('my.index'))->name('login');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.callback');
Route::post('/logout', [SocialAuthController::class, 'logout'])->name('logout');

// AI 상담(연애 코치, 실시간 AI 호출)은 로그인한 사용자만 사용 가능.
// 사주 계산/궁합/상담가이드는 전부 클라이언트에서 계산돼서 여기 안 걸림.
Route::middleware('auth')->prefix('chat')->group(function () {
    Route::get('/', [ChatController::class, 'index']);
    Route::post('/start', [ChatController::class, 'start']);
    Route::get('/{chatSession}', [ChatController::class, 'show']);
    Route::post('/{chatSession}/message', [ChatController::class, 'sendMessage']);
});

// 코인(메시지) 충전 페이지. 토스페이먼츠 키가 없으면 로컬 전용 가짜 결제(purchase)로 자동 대체됨.
// 자세한 흐름은 BillingController 상단 주석 참고.
Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/complete/{payment}', [BillingController::class, 'complete'])->name('billing.complete');
    Route::get('/billing/fail', [BillingController::class, 'fail'])->name('billing.fail');
    Route::post('/billing/purchase', [BillingController::class, 'purchase'])->name('billing.purchase');
});

// 심층 개인 리포트 / 프리미엄 궁합 리포트 (단건 결제 + AI 리포트 생성). 흐름은 위 billing.* 와 동일.
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/reports/checkout', [ReportController::class, 'checkout'])->name('reports.checkout');
    Route::get('/reports/success', [ReportController::class, 'success'])->name('reports.success');
    Route::get('/reports/fail', [ReportController::class, 'fail'])->name('reports.fail');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/status', [ReportController::class, 'status'])->name('reports.status');
    Route::post('/reports/{report}/regenerate', [ReportController::class, 'regenerate'])->name('reports.regenerate');
    // 챕터형(schema_version=2) 리포트에서 챕터 하나만 재시도. 레거시(schema_version=1)
    // 리포트에는 챕터가 없으므로 이 라우트를 쓰지 않습니다(regenerate가 그대로 담당).
    Route::post('/reports/{report}/chapters/{chapterKey}/regenerate', [ReportController::class, 'regenerateChapter'])->name('reports.chapters.regenerate');
});

// 관리자 대시보드(방문자/전환율/매출) + 상담 내용 열람. users.is_admin = true 인 계정만 접근 가능.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/chats', [AdminDashboardController::class, 'chats'])->name('chats');
    Route::get('/chats/{chatSession}', [AdminDashboardController::class, 'chatShow'])->name('chats.show');
});
