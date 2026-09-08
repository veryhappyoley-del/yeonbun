<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\BotDetector;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 홈페이지 로드마다 방문 기록 1행을 남깁니다. 로그인 여부와 무관하게 동작해서
 * 무료로 사주 계산만 쓰는 익명 방문자도 관리자 대시보드의 "방문자 수"에 잡혀요.
 *
 * 브라우저에 오래 유지되는 쿠키(visitor_id)로 "같은 사람이 여러 번 왔는지"를 구분합니다.
 * 정확한 트래킹(광고 유입 경로 세부 분석 등)까지는 아니고, "대략 몇 명이 왔는지" 보는
 * 용도로 충분한 수준의 가벼운 구현이에요.
 *
 * (2026-09-08 수정) "방문자 수가 실제보다 많아 보인다"는 확인 요청으로 user_agent/
 * referrer를 함께 남기고, User-Agent로 봇/크롤러/링크 미리보기 봇을 판별해 is_bot에
 * 기록합니다(App\Support\BotDetector) — 검색엔진 크롤러나 카카오톡 링크 공유 시
 * 미리보기 카드를 만들려고 카카오 서버가 URL을 가져가 보는 것도 매번 새 visitor_id로
 * 잡혀서 방문자 수를 부풀리는 흔한 원인이었습니다. 관리자 대시보드는 이제 이 is_bot
 * 행을 방문자/페이지뷰 집계에서 제외합니다(투명성을 위해 제외된 건수는 따로 보여줌).
 */
class TrackPageView
{
    // (2026-09-08 수정) private -> public — App\Http\Controllers\ChapterPreviewController가
    // "결제 전 미리보기까지 본 사람" 집계(PreviewView)에 같은 방문자 쿠키를 그대로
    // 재사용하려고 이 상수를 참조한다. 쿠키 이름을 두 군데에 따로 하드코딩해두면 나중에
    // 이름을 바꿀 때 한쪽만 고치는 실수가 나기 쉬워서 하나로 합쳤다.
    public const COOKIE = 'yeonbun_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie(self::COOKIE);
        $isNewVisitor = ! $visitorId;

        if ($isNewVisitor) {
            $visitorId = (string) Str::uuid();
        }

        $userAgent = $request->userAgent();

        PageView::create([
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'visitor_id' => $visitorId,
            'user_agent' => $userAgent ? Str::limit($userAgent, 500, '') : null,
            'referrer' => $request->headers->get('referer') ? Str::limit($request->headers->get('referer'), 500, '') : null,
            'is_bot' => BotDetector::isBot($userAgent),
        ]);

        $response = $next($request);

        if ($isNewVisitor) {
            $response->headers->setCookie(
                cookie(self::COOKIE, $visitorId, 60 * 24 * 365) // 1년
            );
        }

        return $response;
    }
}
