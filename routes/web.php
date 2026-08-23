<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('saju');
})->middleware('track.view')->name('home');

// auth 미들웨어가 비로그인 사용자를 리다이렉트할 때 route('login')을 찾다가
// 에러 나지 않도록 이름만 있는 안전장치용 라우트. 실제 로그인 폼은 없고 홈으로 보냄
// (홈에서 카카오/네이버 버튼으로 로그인).
Route::get('/login', fn () => redirect('/'))->name('login');

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
    Route::post('/reports/{report}/regenerate', [ReportController::class, 'regenerate'])->name('reports.regenerate');
});

// 관리자 대시보드(방문자/전환율/매출) + 상담 내용 열람. users.is_admin = true 인 계정만 접근 가능.
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/chats', [AdminDashboardController::class, 'chats'])->name('chats');
    Route::get('/chats/{chatSession}', [AdminDashboardController::class, 'chatShow'])->name('chats.show');
});
