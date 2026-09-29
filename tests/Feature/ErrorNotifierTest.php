<?php

/**
 * 에러 알림 회귀 테스트.
 *
 * 이 기능은 "설정하면 켜지고, 안 하면 아무 일도 안 일어난다"가 전부다. 그 두 가지와
 * "알림이 실패해도 요청을 깨뜨리지 않는다"만 확실히 잠가 두면 된다.
 */

use App\Support\ErrorNotifier;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('웹훅 주소가 없으면 아무것도 보내지 않는다', function () {
    Http::fake();
    config()->set('services.error_webhook.url', null);

    ErrorNotifier::report(new RuntimeException('테스트 예외'));

    Http::assertNothingSent();
});

it('웹훅 주소가 있으면 알림을 보낸다', function () {
    Http::fake();
    config()->set('services.error_webhook.url', 'https://hooks.example.test/abc');

    ErrorNotifier::report(new RuntimeException('테스트 예외'));

    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.example.test/abc'
        && str_contains($request['text'], 'RuntimeException'));
});

it('같은 예외가 연달아 터져도 알림은 한 번만 간다', function () {
    Http::fake();
    config()->set('services.error_webhook.url', 'https://hooks.example.test/abc');

    $throw = function () {
        return new RuntimeException('같은 자리에서 반복되는 예외');
    };

    // 같은 파일·같은 줄에서 만들어진 예외라 지문이 같다.
    ErrorNotifier::report($throw());
    ErrorNotifier::report($throw());
    ErrorNotifier::report($throw());

    Http::assertSentCount(1);
});

it('웹훅 전송이 실패해도 예외를 밖으로 던지지 않는다', function () {
    Http::fake(fn () => throw new ConnectionException('연결 실패'));
    config()->set('services.error_webhook.url', 'https://hooks.example.test/abc');

    ErrorNotifier::report(new RuntimeException('테스트 예외'));
})->throwsNoExceptions();
