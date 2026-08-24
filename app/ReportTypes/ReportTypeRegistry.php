<?php

namespace App\ReportTypes;

/**
 * 판매 가능한 모든 챕터형 리포트 타입(ReportType)의 코드 기반 레지스트리.
 *
 * 기존에는 ReportController::TYPES 상수(하드코딩 배열)와, type==='compat' 이진
 * 스위치가 컨트롤러/뷰/JS 전반에 흩어져 있었습니다. 이 레지스트리는 그걸 대체할
 * 목적으로 만들어졌지만, **1단계(이번 커밋)에서는 아직 어디에도 연결되지 않습니다**
 * — DEFINITIONS가 비어 있고, ReportController는 여전히 기존 TYPES 상수를 씁니다.
 * 이건 의도된 것으로, "레지스트리 인프라만 무해하게 먼저 추가한다"는 1단계 목표에
 * 따른 것입니다. 실제 연결/컷오버는 4~5단계(LoveFortuneReportType/CompatibilityReportType
 * 작성 후)에서 이뤄집니다.
 *
 * 참고: 기존 single/compat(레거시, schema_version=1) 리포트는 이 레지스트리에
 * 절대 등록되지 않습니다 — 이미 결제한 고객의 리포트는 ReportController::TYPES 및
 * 기존 ReportGenerator/single-report.blade.php 경로로 영구히 그대로 서비스됩니다.
 */
final class ReportTypeRegistry
{
    /**
     * @var array<int, class-string<ReportTypeDefinition>>
     */
    private const DEFINITIONS = [
        // 4단계에서 추가 예정:
        // \App\ReportTypes\Definitions\LoveFortuneReportType::class,
        // \App\ReportTypes\Definitions\CompatibilityReportType::class,
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
