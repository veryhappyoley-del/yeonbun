<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "프리미엄 궁합 리포트" / "심층 개인 리포트" 단건 결제 1건당 1행.
// 흐름은 payments 테이블과 거의 같습니다: 결제창 띄우기 직전 status=pending으로 생성 →
// 토스 결제 승인(confirm) 성공 시에만 status=paid로 바꾸고, 그 직후 서버가 Anthropic API를
// 한 번 호출해서 심층 해석(content)을 생성해 저장합니다. input에는 프론트(app.js)에서
// 이미 계산해 둔 사주/궁합 요약(JSON)이 들어가며, 여기엔 사주 원리 계산 로직이 전혀 없습니다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // single | compat
            $table->string('order_id')->unique();
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending'); // pending | paid | failed
            $table->string('payment_key')->nullable();
            $table->string('title')->nullable();
            $table->json('input');
            $table->longText('content')->nullable(); // AI가 생성한 리포트 본문(HTML 일부 태그만 허용, 저장 전 sanitize)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
