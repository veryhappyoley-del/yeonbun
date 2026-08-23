<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * 홈페이지 로드마다 방문 기록 1행을 남깁니다. 로그인 여부와 무관하게 동작해서
 * 무료로 사주 계산만 쓰는 익명 방문자도 관리자 대시보드의 "방문자 수"에 잡혀요.
 *
 * 브라우저에 오래 유지되는 쿠키(visitor_id)로 "같은 사람이 여러 번 왔는지"를 구분합니다.
 * 정확한 트래킹(광고 유입 경로, 봇 필터링 등)까지는 아니고, "대략 몇 명이 왔는지" 보는
 * 용도로 충분한 수준의 가벼운 구현이에요.
 */
class TrackPageView
{
    private const COOKIE = 'yeonbun_visitor';

    public function handle(Request $request, Closure $next): Response
    {
        $visitorId = $request->cookie(self::COOKIE);
        $isNewVisitor = ! $visitorId;

        if ($isNewVisitor) {
            $visitorId = (string) Str::uuid();
        }

        PageView::create([
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'visitor_id' => $visitorId,
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
