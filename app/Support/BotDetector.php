<?php

namespace App\Support;

/**
 * (2026-09-08 신설) User-Agent 문자열로 "사람이 브라우저로 직접 들어온 것"이 아닌
 * 트래픽을 걸러낸다 — 검색엔진 크롤러, 카카오톡/페이스북/슬랙 등의 링크 미리보기
 * 생성 봇, 업타임 모니터링, 스크래퍼/CLI 도구까지 포함한다.
 *
 * 완벽한 판별은 불가능하다(모든 봇이 UA를 정직하게 밝히는 건 아니다). 목적은 "관리자
 * 대시보드 방문자 수가 실제 사람 수와 크게 어긋나는 흔한 원인"을 없애는 것이지, 완벽한
 * 봇 차단이 아니다 — 그래서 접근을 막거나 응답을 바꾸는 데는 안 쓰고, 통계에서만
 * 제외하는 용도로 App\Http\Middleware\TrackPageView가 저장 시점에 한 번만 판별해서
 * page_views.is_bot에 기록해 둔다.
 *
 * 주의: 카카오톡/인스타그램 등 "인앱 브라우저"(실제 사람이 그 안에서 링크를 탭해서
 * 들어온 경우)는 여기서 봇으로 치지 않는다 — 그건 진짜 방문이고, 다만 쿠키가 잘 안
 * 남아서 같은 사람이 여러 번 온 것처럼 잡힐 뿐이다(관리자 대시보드에 별도 안내로 표시).
 * "kakaotalk-scrap"처럼 카카오 서버가 링크 미리보기 카드를 만들려고 서버끼리 URL을
 * 가져가 보는 것만 봇으로 분류한다.
 */
class BotDetector
{
    /**
     * 소문자로 비교하는 부분 문자열 목록. 오탐 위험이 낮은(일반 브라우저 UA에 등장할
     * 여지가 거의 없는) 키워드만 골랐다 — "app"처럼 흔한 단어는 넣지 않았다.
     */
    private const SIGNATURES = [
        // 검색엔진 크롤러
        'googlebot', 'bingbot', 'yandexbot', 'yandex', 'baiduspider', 'duckduckbot',
        'yeti', 'naver.me/bot', 'daumoa', 'sogou', 'exabot', 'ia_archiver',
        // 링크 미리보기 생성 봇(사람이 아니라 서버가 URL을 미리 가져가 보는 것)
        'facebookexternalhit', 'kakaotalk-scrap', 'slackbot', 'telegrambot',
        'twitterbot', 'discordbot', 'linkedinbot', 'whatsapp', 'skypeuripreview',
        'vkshare', 'w3c_validator',
        // SEO/보안 스캐너·업타임 모니터링
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'petalbot', 'bytespider',
        'uptimerobot', 'pingdom', 'statuscake', 'site24x7', 'gtmetrix',
        // 범용 크롤러/스크래퍼 서명
        'bot/', 'spider', 'crawler', 'crawl/',
        // 브라우저가 아닌 CLI/라이브러리로 직접 호출한 경우
        'curl/', 'wget/', 'python-requests', 'python-urllib', 'axios/', 'okhttp',
        'go-http-client', 'java/', 'libwww-perl', 'postmanruntime', 'headlesschrome',
        'phantomjs',
    ];

    public static function isBot(?string $userAgent): bool
    {
        if (! $userAgent || trim($userAgent) === '') {
            // UA가 아예 없는 요청은 오래된 봇이거나 UA를 숨긴 스크립트일 수도 있지만,
            // 일부 인앱 브라우저/보안 소프트웨어도 UA를 비우는 경우가 있어 단정하지
            // 않는다(오탐으로 실제 방문자를 지우는 쪽이 더 나쁘다고 판단).
            return false;
        }

        $ua = mb_strtolower($userAgent);

        foreach (self::SIGNATURES as $signature) {
            if (str_contains($ua, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * (통계 참고용) 카카오톡/인스타그램/네이버 등 "인앱 브라우저"로 추정되는지.
     * 봇이 아니라 실제 사람이지만, 이런 인앱 브라우저는 쿠키가 자주 초기화돼서 같은
     * 사람이 여러 번 온 것처럼 방문자 수에 잡히는 대표적인 원인이라 관리자 대시보드에
     * 참고 지표로만 따로 보여준다(방문자 수 집계에서 제외하지는 않음).
     */
    public static function isInAppBrowser(?string $userAgent): bool
    {
        if (! $userAgent) {
            return false;
        }

        $ua = mb_strtolower($userAgent);

        foreach (['kakaotalk', 'fban', 'fbav', 'instagram', 'line/', 'naver(inapp'] as $signature) {
            if (str_contains($ua, $signature)) {
                return true;
            }
        }

        return false;
    }
}
