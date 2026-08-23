<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 카카오/네이버 로그인만 지원하므로 비밀번호 대신 (provider, provider_id) 조합으로
     * 사용자를 식별합니다. 같은 사람이 카카오·네이버 두 곳에서 같은 이메일을 쓸 수도 있어서
     * email 의 unique 제약은 풀고, 대신 (provider, provider_id) 를 유일 키로 둡니다.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('email');
            $table->string('provider_id')->nullable()->after('provider');
            $table->dropUnique(['email']);
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_id']);
            $table->unique('email');
            $table->dropColumn(['provider', 'provider_id']);
        });
    }
};
