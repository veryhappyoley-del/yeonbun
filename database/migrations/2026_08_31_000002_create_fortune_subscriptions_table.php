<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "오늘의 운세" 정기결제 구독 1건당 1행(user_id 1:1 — 한 사람이 여러 구독을 가질
// 필요는 아직 없음). BillingController/Payment의 "클라이언트 값을 절대 그대로 믿지
// 않고 서버가 토스 API를 직접 호출해 확인한다"는 원칙을 그대로 따르되, 1회성이 아니라
// 매달 반복 청구해야 해서 토스 "빌링(자동결제)" 방식의 billing_key를 저장해둔다.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fortune_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending | active | past_due | canceled
            $table->string('toss_customer_key')->nullable();
            $table->string('toss_billing_key')->nullable();
            $table->unsignedInteger('price');
            $table->date('next_billing_date')->nullable();
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_billing_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fortune_subscriptions');
    }
};
