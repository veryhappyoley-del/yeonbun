<?php

// (2026-10-07) 토스페이먼츠 심사용 임시 설정 — 비로그인 리포트 결제 허용 여부.
// 운영 .env에 ALLOW_GUEST_CHECKOUT=true 를 넣으면 켜지고, 심사가 끝나면 false(또는 삭제)로
// 되돌린 뒤 재배포하면 원래대로(로그인 필수) 돌아간다. config()로 읽는 이유: 운영에서
// config:cache가 켜져 있으면 코드 안의 env() 직접 호출은 null을 반환하기 때문.
return [
    'checkout' => (bool) env('ALLOW_GUEST_CHECKOUT', false),
];
