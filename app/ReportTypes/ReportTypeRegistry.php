<?php

namespace App\ReportTypes;

/**
 * 판매 가능한 모든 챕터형 리포트 타입(ReportType)의 코드 기반 레지스트리.
 *
 * 예전에는 ReportController::TYPES 상수(하드코딩 배열)와 type==='compat' 이진 스위치가
 * 컨트롤러/뷰/JS 전반에 흩어져 있었습니다. 5단계(컷오버)부터 ReportController가 이
 * 레지스트리를 실제로 참조합니다 — checkout()은 여기 등록된 타입만 새로 판매하고,
 * show()/status()는 schema_version=2 리포트의 챕터 구성을 여기서 조회합니다.
 *
 * 참고: 기존 single/compat(레거시, schema_version=1) 리포트는 이 레지스트리에
 * 절대 등록되지 않습니다 — 이미 결제한 고객의 리포트는 ReportController::LEGACY_TYPES
 * 및 기존 ReportGenerator/single-report.blade.php 경로로 영구히 그대로 서비스됩니다.
 */
final class ReportTypeRegistry
{
    /**
     * @var array<int, class-string<ReportTypeDefinition>>
     */
    private const DEFINITIONS = [
        \App\ReportTypes\Definitions\LoveFortuneReportType::class,
        \App\ReportTypes\Definitions\CompatibilityReportType::class,
        // (2026-08-31 추가) "짝사랑의 다음 장" — 대운/세운 계산이 필요한 유일한 타입이라
        // public/js/luck-cycle.js가 계산한 timingCandidates를 입력으로 함께 보낸다.
        \App\ReportTypes\Definitions\UnrequitedLoveReportType::class,
        // (2026-08-31 추가) "다시, 우리" — InputShape::TwoPersonWithHistory를 처음 실제로
        // 쓰는 타입. luck-cycle.js의 monthlyCalendar()로 12개월 전체를 계산해서 입력으로
        // 함께 보낸다(재회 타이밍 캘린더 챕터).
        \App\ReportTypes\Definitions\ReunionStrategyReportType::class,
    ];

    /**
     * @return array<string, ReportType> 리포트 타입 키를 인덱스로 하는 배열.
     */
    public static function all(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $types = [];

        foreach (self::DEFINITIONS as $definitionClass) {
            $type = $definitionClass::make();
            $types[$type->key] = $type;
        }

        return $cache = $types;
    }

    public static function get(string $key): ?ReportType
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
