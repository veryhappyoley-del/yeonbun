<?php

namespace App\ReportTypes;

/**
 * ReportType이 checkout() 시점에 프론트(reports.js)로부터 어떤 모양의 input을
 * 받는지를 선언합니다. 실제 유효성 검증 로직을 갖진 않고(그건 여전히 프론트/
 * ReportController가 함), 어떤 build*Input() 함수를 써야 하는지 문서화하는
 * 용도 + 나중에 InputShape별 공통 처리를 추가할 때의 분기 기준으로 씁니다.
 */
enum InputShape: string
{
    // 본인 한 명의 deep 사주 데이터만 필요 (연애운분석, 재물성장전략, 직업성공전략).
    case Self = 'self';

    // 본인 + 상대방 두 명의 deep 사주 데이터가 모두 필요 (궁합분석).
    case TwoPerson = 'two_person';

    // 본인 + 전 연인 두 명의 deep 사주 데이터 + 교제/이별 히스토리(교제 기간, 이별
    // 시점/주도자/사유 등)가 필요 (재회전략, 3단계에서 실제로 쓰기 시작함).
    case TwoPersonWithHistory = 'two_person_with_history';
}
