<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /admin/* 는 이 미들웨어 + auth 미들웨어를 같이 통과해야 접근 가능합니다.
 * 관리자 지정은 users.is_admin 컬럼(기본 false)으로 하고, 아래 명령으로 직접 켜주세요.
 *   php artisan tinker
 *   >>> \App\Models\User::where('name', '올리')->update(['is_admin' => true]);
 */
class EnsureIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_admin, 403, '관리자만 접근할 수 있어요.');

        return $next($request);
    }
}
