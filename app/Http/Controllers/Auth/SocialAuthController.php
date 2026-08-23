<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['kakao', 'naver'];

    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        $socialUser = Socialite::driver($provider)->user();

        $user = User::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName()
                    ?: $socialUser->getNickname()
                    ?: ($provider === 'kakao' ? '카카오' : '네이버').' 사용자',
                // 카카오는 이메일 동의를 안 하면 null일 수 있어서, 없으면 provider+id로 만든
                // 내부용 더미 이메일을 넣어요. 로그인 식별은 어차피 provider+provider_id로 해요.
                'email' => $socialUser->getEmail() ?: "{$provider}_{$socialUser->getId()}@yeonbun.local",
                'password' => Hash::make(Str::random(40)),
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ]);
        }

        Auth::login($user, remember: true);

        return redirect('/');
    }

    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }

    private function ensureProviderIsAllowed(string $provider): void
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS, true), Response::HTTP_NOT_FOUND);
    }
}
