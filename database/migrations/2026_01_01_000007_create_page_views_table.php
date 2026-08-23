<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 홈페이지 로드 1회당 1행. visitor_id는 로그인 여부와 무관하게 브라우저 쿠키로 구분하는
// 익명 방문자 식별자라서, 로그인 없이 무료 탭만 쓰는 사람도 방문 수에 잡힙니다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('visitor_id', 36);
            $table->timestamps();

            $table->index('visitor_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
