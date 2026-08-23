<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // 기존에 익명으로 만들어졌던 세션이 있을 수 있어 nullable로 두되,
            // 로그인 이후 새로 만드는 세션은 컨트롤러에서 항상 값을 채웁니다.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
