<?php

/**
 * 전자상거래법(전자상거래 등에서의 소비자보호에 관한 법률) 제10조에 따라
 * 통신판매(=온라인 결제를 받는) 사업자는 홈페이지에 상호/대표자/사업자등록번호/
 * 통신판매업 신고번호/주소/연락처를 표시해야 합니다.
 *
 * 여기 값들은 전부 .env에서 읽어옵니다. 아직 사업자등록을 하지 않았다면 .env에
 * BUSINESS_REG_NO를 비워두세요 — reg_no가 비어있으면 화면에 아무것도 표시되지
 * 않습니다(테스트 단계에서 빈 정보를 보여주지 않기 위함). 실제 사업자등록번호가
 * 생기면 .env를 채우기만 하면 자동으로 footer에 노출됩니다.
 */
return [
    'name' => env('BUSINESS_NAME'),
    'owner' => env('BUSINESS_OWNER'),
    'reg_no' => env('BUSINESS_REG_NO'),
    'mail_order_no' => env('BUSINESS_MAIL_ORDER_NO'),
    'address' => env('BUSINESS_ADDRESS'),
    'phone' => env('BUSINESS_PHONE'),
    'email' => env('BUSINESS_EMAIL'),

    // (2026-08-26 추가) 푸터 인스타그램 아이콘이 가리킬 계정 URL. 비어있으면
    // 아이콘 자체를 숨긴다(다른 사업자 정보 항목들과 동일한 "값 없으면 표시 안 함" 원칙).
    'instagram' => env('BUSINESS_INSTAGRAM'),
];
