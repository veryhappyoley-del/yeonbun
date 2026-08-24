<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'anthropic' => [
        // console.anthropic.com 에서 발급받은 API 키를 .env 의 ANTHROPIC_API_KEY 에 넣으세요.
        'key' => env('ANTHROPIC_API_KEY'),
        // 모델 ID는 시간이 지나면 바뀔 수 있어요. 최신 목록은
        // https://platform.claude.com/docs/en/about-claude/models/overview 참고.
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
        // 챕터형(schema_version=2) 리포트에서 Http::pool()로 한 번에 동시 요청할 챕터 수.
        // 너무 크면 Anthropic 쪽 rate limit에 걸릴 수 있고, 너무 작으면 병렬 이득이 줄어듭니다 —
        // 실측 후(report_chapters.output_tokens/생성 총 소요 시간) 튜닝하세요.
        'chapter_concurrency' => (int) env('ANTHROPIC_CHAPTER_CONCURRENCY', 4),
    ],

    // 카카오 개발자(developers.kakao.com)에서 발급. "카카오 로그인" 활성화 + Redirect URI 등록 필요.
    'kakao' => [
        'client_id' => env('KAKAO_CLIENT_ID'),
        'client_secret' => env('KAKAO_CLIENT_SECRET'),
        'redirect' => env('KAKAO_REDIRECT_URI'),
    ],

    // 네이버 개발자센터(developers.naver.com)에서 발급. 애플리케이션 등록 + Callback URL 등록 필요.
    'naver' => [
        'client_id' => env('NAVER_CLIENT_ID'),
        'client_secret' => env('NAVER_CLIENT_SECRET'),
        'redirect' => env('NAVER_REDIRECT_URI'),
    ],

    // 토스페이먼츠 개발자센터(developers.tosspayments.com)에서 발급.
    // client_key는 브라우저(JS)에 노출돼도 되는 공개키, secret_key는 서버에서만 써야 하는 비밀키입니다.
    // 둘 다 비워두면 코인 충전 페이지가 자동으로 "로컬 테스트용 즉시 지급" 방식으로 동작합니다.
    'toss' => [
        'client_key' => env('TOSS_CLIENT_KEY'),
        'secret_key' => env('TOSS_SECRET_KEY'),
    ],

];
