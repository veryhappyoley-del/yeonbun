<?php

namespace App\ReportTypes;

/**
 * app/ReportTypes/Definitions/ 아래의 각 리포트 타입 정의 클래스가 구현하는 계약.
 * ReportTypeRegistry::DEFINITIONS에 클래스명을 등록해두면 make()가 호출되어
 * ReportType 인스턴스로 조립됩니다(1단계에서는 아직 등록된 정의가 없습니다 —
 * 4단계에서 LoveFortuneReportType/CompatibilityReportType이 이 계약을 구현합니다).
 */
interface ReportTypeDefinition
{
    public static function make(): ReportType;
}
