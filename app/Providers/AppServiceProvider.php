<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Kakao\KakaoProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Naver\Provider as NaverProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 카카오/네이버는 Socialite 기본 제공 드라이버가 아니라서
        // socialiteproviders/kakao, socialiteproviders/naver 패키지로 확장합니다.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('kakao', KakaoProvider::class);
            $event->extendSocialite('naver', NaverProvider::class);
        });
    }
}
