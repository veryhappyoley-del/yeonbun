<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 결제 전 "무료 미리보기"용 챕터 캐시. report_chapters와 거의 같은 모양이지만 report_id에
// 묶이지 않고(아직 Report 행 자체가 없는 시점이라) (report_type, chapter_key, input_hash)
// 조합으로 재사용됩니다 — 같은 두 사람 조합(+관계 단계/관심사)으로 다시 요청하면 API를
// 다시 부르지 않고 이미 만든 걸 그대로 돌려줍니다.
//
// input_hash는 App\Services\ChapterGenerator::previewInputHash()가 "그 챕터가 실제로
// 쓰는 입력값 + 챕터 스키마/프롬프트 내용"을 함께 해시로 묶어서 만듭니다 — 그래서
// ChapterSpec의 스키마나 프롬프트를 나중에 고치면(이번 세션에서도 여러 번 그랬듯이)
// 해시가 자동으로 달라져서 옛날 캐시가 저절로 무효화되고, 별도로 버전 번호를 관리할
// 필요가 없습니다.
//
// input 컬럼은 실제 API 요청을 다시 만들 때 필요한 원본 입력(Report.input과 같은 모양)을
// 그대로 저장합니다 — 결제로 이어지면 GenerateReportJob이 이 행의 content를 그대로 복사해
// 쓰고, 결제로 이어지지 않으면 (배치 정리 명령으로) 일정 시간 뒤 삭제됩니다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_previews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('report_type'); // ReportType::$key, 예: 'compatibility'
            $table->string('chapter_key'); // ChapterSpec::$key, 예: 'compat_overview'
            $table->string('input_hash', 64); // sha256 hex
            $table->json('input'); // 원본 입력(필터링 전) — 재사용 시 requestPayload() 재구성용
            $table->string('status')->default('pending'); // pending | generating | ready | failed
            $table->longText('content')->nullable();
            $table->string('stop_reason')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['report_type', 'chapter_key', 'input_hash'], 'chapter_previews_lookup_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_previews');
    }
};
