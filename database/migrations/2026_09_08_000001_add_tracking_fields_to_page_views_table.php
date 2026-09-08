<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// (2026-09-08 추가) "관리자 대시보드 방문자 수가 실제보다 부풀려진 게 아니냐"는 확인
// 요청 대응 — 지금까지는 UA/리퍼러를 아예 안 남겨서 어떤 방문이 진짜 사람인지,
// 크롤러/링크 미리보기 봇인지 사후에 전혀 구분할 수 없었다. 세 컬럼을 추가한다.
//   - user_agent: 봇/크롤러 판별(App\Support\BotDetector)에 씀. 이미 privacy.blade.php에
//     "브라우저(User-Agent) 정보" 수집 항목이 명시돼 있어 별도 고지 없이 추가.
//   - referrer: 유입 경로(어느 사이트에서 왔는지) — 관리자 대시보드의 "유입 경로" 집계용.
//   - is_bot: user_agent로 판별한 결과를 매 요청마다 다시 계산하지 않도록 저장해 둔다.
//     기존 행은 판별 자료(user_agent)가 없어서 전부 false로 남는다(과거 데이터를 봇으로
//     잘못 재분류하지 않기 위해 일부러 재계산하지 않음 — 새로 쌓이는 데이터부터 정확해짐).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('user_agent', 500)->nullable()->after('visitor_id');
            $table->string('referrer', 500)->nullable()->after('user_agent');
            $table->boolean('is_bot')->default(false)->after('referrer');

            $table->index('is_bot');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropIndex(['is_bot']);
            $table->dropColumn(['user_agent', 'referrer', 'is_bot']);
        });
    }
};
