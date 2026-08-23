<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 대화가 길어졌을 때(기본 25턴 이상) 오래된 구간을 요약해서 압축하기 위한 컬럼.
 * history_summary: 오래된 구간을 요약한 텍스트(짧게 유지됨, 계속 자라지 않음).
 * summarized_count: chat_messages 중 몇 번째까지가 이미 history_summary에 반영됐는지.
 * ChatController::compressHistoryIfNeeded() 참고.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->text('history_summary')->nullable()->after('saju_context');
            $table->unsignedInteger('summarized_count')->default(0)->after('history_summary');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn(['history_summary', 'summarized_count']);
        });
    }
};
