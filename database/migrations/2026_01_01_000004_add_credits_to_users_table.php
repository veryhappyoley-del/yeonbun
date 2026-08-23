<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// AI 상담(연애 코치)에 쓸 수 있는 메시지 개수를 코인처럼 관리합니다.
// 신규 가입 시 10개를 무료로 지급해서 "맛보기"를 하게 하고, 다 쓰면 /billing에서 충전합니다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('credits')->default(10)->after('provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credits');
        });
    }
};
