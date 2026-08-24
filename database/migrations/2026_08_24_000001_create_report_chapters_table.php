<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// schema_version=2(챕터형) 리포트의 챕터 하나당 1행. reports.content(블롭) 대신
// 챕터마다 독립된 status/content/재시도 상태를 가져서, 챕터 하나가 실패해도
// 나머지 19개는 정상적으로 ready 상태를 유지할 수 있습니다(2단계 GenerateReportJob이
// Http::pool()로 채워 넣고, ReportController::regenerateChapter()가 개별 재시도합니다).
//
// 레거시(schema_version=1) 리포트는 이 테이블을 전혀 쓰지 않습니다 — 계속
// reports.content에 저장됩니다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_chapters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('report_id')->constrained('reports')->cascadeOnDelete();
            $table->string('chapter_key'); // ChapterSpec::$key와 매칭
            $table->unsignedSmallInteger('sort_order');
            $table->string('title'); // 생성 시점 스냅샷 — 이후 ChapterSpec 정의가 바뀌어도
                                      // 이미 판매된 리포트의 챕터 제목은 그대로 유지됩니다.
            $table->string('status')->default('pending'); // pending | generating | ready | failed
            $table->longText('content')->nullable(); // AI가 생성한 이 챕터의 JSON(배열로 캐스팅)
            $table->string('stop_reason')->nullable(); // Anthropic 응답의 stop_reason(진단용)
            $table->unsignedInteger('output_tokens')->nullable(); // 실측 토큰 사용량(가격 재검토용)
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['report_id', 'chapter_key']);
            $table->index(['report_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_chapters');
    }
};
