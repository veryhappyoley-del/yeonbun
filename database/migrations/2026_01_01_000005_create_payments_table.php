<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 토스페이먼츠 결제 1건당 1행. "결제창을 띄우기 직전"에 status=pending 으로 먼저 만들어두고,
// 토스의 결제 승인(confirm) API가 성공했을 때만 status=paid 로 바꾸면서 credits를 지급합니다.
// (클라이언트가 보내는 값만 믿고 바로 크레딧을 주면 위조 요청으로 무료 충전이 가능해지기 때문입니다.)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan');
            $table->string('order_id')->unique();
            $table->unsignedInteger('credits');
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending'); // pending | paid | failed
            $table->string('payment_key')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
