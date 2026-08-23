<?php

use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

// 연분(緣分) AI 연애 상담 챗봇 API
// - 프론트(resources/views/saju.blade.php)의 "AI 상담" 탭이 이 두 엔드포인트를 호출합니다.
// - 실제 대화는 서버에서 Anthropic(Claude) API를 호출해 만들어지며, API 키는
//   .env 의 ANTHROPIC_API_KEY 에만 저장되고 브라우저로는 절대 전달되지 않습니다.
Route::prefix('chat')->group(function () {
    Route::post('/start', [ChatController::class, 'start']);
    Route::post('/{chatSession}/message', [ChatController::class, 'sendMessage']);
});
