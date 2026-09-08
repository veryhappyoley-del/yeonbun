<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// (2026-09-08 신설) "결제 전, 정보를 입력하고 무료 미리보기를 본 사람"까지 관리자
// 대시보드 퍼널에 넣어 달라는 요청 대응.
//
// chapter_previews(App\Models\ChapterPreview)는 (report_type, chapter_key, input_hash)로
// 캐시를 다시 쓰기 위한 테이블이라, 몇 "명"이 봤는지와는 다른 걸 센다 — 프론트가 상태
// 폴링에도 같은 엔드포인트를 재사용해서 같은 사람이 여러 번 호출해도 새 행이 안 생기고
// (firstOrCreate라 정상), 반대로 서로 다른 두 사람이 우연히 같은 입력값(생년월일시/성별/
// 관심사 조합)을 넣으면 캐시 하나를 공유해서 실제로는 2명인데 1건으로 잡힌다.
//
// 그래서 "방문자 수(page_views)"와 같은 방식 — 브라우저 쿠키(yeonbun_visitor)로 사람을
// 구분 — 을 여기도 그대로 적용한 별도 테이블을 둔다. visitor_id + report_type +
// chapter_key 조합에 유니크 제약을 걸어서, 같은 사람이 같은 타입의 미리보기를 여러 번
// 요청/폴링해도(입력을 바꿔서 다시 본 경우가 아니면) 딱 1행만 남는다 — "이 방문자가 이
// 타입의 미리보기 단계까지 도달한 적이 있다"는 최초 시점만 기록하는 셈이라, 관리자
// 대시보드에서 기간별로 "이 기간에 새로 미리보기까지 본 사람 수"를 셀 수 있다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preview_views', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id', 36);
            $table->string('report_type');
            $table->string('chapter_key');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent', 500)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamps();

            $table->unique(['visitor_id', 'report_type', 'chapter_key'], 'preview_views_visitor_type_chapter_unique');
            $table->index('created_at');
            $table->index('report_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preview_views');
    }
};
