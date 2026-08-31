<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "오늘의 운세" 구독 신설(2026-08-31)에 맞춰 처음 만드는 테이블. 지금까지는 사주
// 계산이 전부 브라우저 JS에서만 이뤄지고(계산기를 쓸 때마다 매번 새로 입력) 서버에
// 생년월일시를 저장해둔 적이 없었다 — 구독은 사용자가 접속 안 해도 서버가 매일 새벽에
// 알아서 오늘의 운세를 만들어야 하므로, 이번에 처음으로 "저장된 내 사주 정보"가
// 필요해졌다. user_id 1:1(구독 안 해도 나중에 계산기 자동완성 등에 재사용 가능하도록
// 별도 테이블로 분리 — users 테이블에 바로 컬럼을 얹지 않음).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saju_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->date('birth_date');
            $table->unsignedTinyInteger('birth_hour')->nullable();
            $table->unsignedTinyInteger('birth_minute')->nullable();
            $table->boolean('birth_time_unknown')->default(false);
            $table->string('gender'); // male | female — 오늘의 운세 문구 톤 정도에만 씀
            $table->string('sido')->nullable();
            $table->string('sigungu')->nullable();
            $table->decimal('longitude', 6, 3)->nullable(); // sigungu 매칭 시점의 경도 스냅샷
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saju_profiles');
    }
};
