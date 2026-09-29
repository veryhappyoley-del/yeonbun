<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 운영 중 발생한 예외를 실시간으로 알려주는 최소 구성 (2026-09-28 신설).
 *
 * 배경: 출시전 점검리스트 3항 — 에러 모니터링이 전혀 없어서, 결제·AI 생성처럼 돈이
 * 걸린 기능이 터져도 서버 로그 파일을 직접 열어봐야 알 수 있었다.
 *
 * 왜 Sentry가 아니라 이것인가: Sentry는 composer 패키지 설치가 필요한데, 지금 단계에서
 * 의존성을 하나 더 얹기보다 "설정 한 줄로 켜지고, 안 켜면 아무 일도 안 일어나는" 쪽을
 * 먼저 둔다. ERROR_WEBHOOK_URL(.env)에 슬랙/디스코드 Incoming Webhook 주소를 넣으면
 * 그 순간부터 알림이 가고, 비워두면 기존처럼 로그만 남는다.
 * 나중에 Sentry로 옮기려면 bootstrap/app.php의 report 콜백만 교체하면 된다.
 *
 * 설계상 지키는 것:
 *  - 알림 전송이 실패해도 원래 요청을 절대 깨뜨리지 않는다(예외를 삼키고 로그만 남긴다).
 *  - 같은 예외가 폭주할 때 웹훅을 도배하지 않도록 지문(fingerprint) 단위로 쿨다운을 둔다.
 *  - 메시지에 사용자 입력 원문을 싣지 않는다(생년월일시 등 개인정보가 웹훅으로 새지 않게).
 */
class ErrorNotifier
{
    /** 같은 예외를 다시 알리기까지의 최소 간격(초). */
    private const COOLDOWN_SECONDS = 300;

    public static function report(Throwable $e): void
    {
        $url = config('services.error_webhook.url');

        if (! $url) {
            return; // 설정 안 하면 아무 일도 하지 않는다 — 기존 로그 동작 그대로.
        }

        try {
            $fingerprint = sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
            $cacheKey = 'error-notify:'.$fingerprint;

            // add()는 키가 없을 때만 true를 돌려주는 원자적 연산이라, 동시에 여러 요청이
            // 같은 예외로 터져도 알림은 한 번만 나간다.
            if (! cache()->add($cacheKey, true, self::COOLDOWN_SECONDS)) {
                return;
            }

            Http::timeout(3)->post($url, [
                'text' => self::format($e),
            ]);
        } catch (Throwable $inner) {
            // 알림이 실패했다고 사용자 요청까지 깨뜨리면 본말전도다.
            Log::warning('에러 알림 전송 실패', ['message' => $inner->getMessage()]);
        }
    }

    private static function format(Throwable $e): string
    {
        $request = request();

        return implode("\n", array_filter([
            '🚨 '.config('app.name').' 예외 발생 ('.config('app.env').')',
            get_class($e).': '.\Illuminate\Support\Str::limit($e->getMessage(), 300),
            $e->getFile().':'.$e->getLine(),
            // 경로만 남긴다 — 쿼리스트링에는 주문번호·개인정보가 섞일 수 있다.
            $request ? ($request->method().' '.$request->path()) : null,
            $request && $request->user() ? 'user_id: '.$request->user()->id : null,
        ]));
    }
}
