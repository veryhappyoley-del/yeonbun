<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'track.view' => \App\Http\Middleware\TrackPageView::class,
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // (2026-09-28 추가) 에러 모니터링. 지금까지는 예외가 나도 서버 로그 파일을 직접
        // 열어봐야 알 수 있었다(출시전 점검리스트 3항). ERROR_WEBHOOK_URL을 채우면
        // 슬랙/디스코드로 바로 알림이 가고, 비워두면 예전과 똑같이 로그만 남는다.
        // 나중에 Sentry로 옮길 때는 이 콜백 안만 바꾸면 된다.
        $exceptions->report(function (\Throwable $e): void {
            \App\Support\ErrorNotifier::report($e);
        });
    })->create();
