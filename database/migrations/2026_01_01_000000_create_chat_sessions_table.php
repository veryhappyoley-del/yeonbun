<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "AI 상담" 탭에서 시작되는 한 번의 대화 세션.
     * saju_context 에는 프론트에서 이미 계산해 둔 사주 요약(일간 오행, 오행 분포,
     * 신살 등)을 저장해서, 매 메시지마다 이 값을 바탕으로 시스템 프롬프트를 구성합니다.
     */
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->json('saju_context')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
