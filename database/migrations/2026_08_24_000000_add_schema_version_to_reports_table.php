<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 리포트가 "레거시 단일 블롭"(1) 방식인지 "챕터형"(2) 방식인지 구분하는 컬럼.
// 기본값 1이라 기존에 이미 저장된 모든 row(및 이 마이그레이션 이후에도 당분간
// 계속 팔리는 기존 single/compat 상품)는 자동으로 레거시 경로를 그대로 탑니다 —
// 즉 이 마이그레이션 자체는 어떤 기존 동작도 바꾸지 않는, 순수 스키마 추가입니다.
// checkout() 시점에 ReportTypeRegistry에 등록된(챕터형) 타입이면 명시적으로 2를
// 세팅하게 됩니다(5단계 컷오버에서 연결).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->unsignedTinyInteger('schema_version')->default(1)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('schema_version');
        });
    }
};
