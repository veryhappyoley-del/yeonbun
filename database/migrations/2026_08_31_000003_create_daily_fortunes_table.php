<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "오늘의 운세" 하루치 1행. report_chapters와 같은 status 패턴(pending/generating/
// ready/failed)을 재사용해서, 매일 새벽 배치(GenerateDailyFortunes 커맨드)가 실패해도
// 그 날짜만 failed로 남고 다음 날 배치나 수동 재시도에 영향을 안 준다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_fortunes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('fortune_date');
            $table->string('status')->default('pending'); // pending | generating | ready | failed
            $table->json('content')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable(); // 이메일 발송 완료 시각
            $table->timestamps();

            $table->unique(['user_id', 'fortune_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_fortunes');
    }
};
