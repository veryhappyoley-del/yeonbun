<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    private const ALLOWED_PROVIDERS = ['kakao', 'naver'];

    // (2026-08-25 추가, 로드맵 1·2번) 로그인 전 보고 있던 페이지로 돌아가기 위한 진입점.
    // 라우트 미들웨어(auth)가 비로그인 사용자를 걷어낼 때는 Laravel이 자동으로
    // session('url.intended')를 채워두지만, 이 사이트의 로그인 유도는 대부분 "페이지 안
    // 인라인 게이트"(연애 코치 탭, 리포트 구매 버튼)라서 그 자동 메커니즘이 동작하지 않는다.
    // 그래서 링크를 만드는 쪽(saju.blade.php, reports.js)에서 ?redirect=현재경로 를 붙여
    // 보내주면, 여기서 검증 후 같은 session 키(url.intended)에 수동으로 채워 넣어
    // callback()의 redirect()->intended()가 그대로 활용하게 한다.
    public function redirect(string $provider, Request $request): RedirectResponse
    {
        $this->ensureProviderIsAllowed($provider);

        $redirectTo = $request->query('redirect');
        if (is_string($redirectTo) && $this->isSafeLocalRedirect($redirectTo)) {
            $request->session()->put('url.intended', $redirectTo);
        }

        return Socialite::driver($provider)->redirect();
    }

    // redirect 쿼리 파라미터는 사용자가 값을 조작할 수 있는 입력이라, 그대로 믿고
    // redirect()에 넘기면 오픈 리다이렉트 취약점이 된다(예: ?redirect=https://evil.example
    // 나 ?redirect=//evil.example 로 외부 사이트로 보내버릴 수 있음). "/"로 시작하는 같은
    // 사이트 내 상대 경로만 허용하고, 프로토콜 상대 경로(//)나 스킴 포함(://) 값은 전부 거른다.
    private function isSafeLocalRedirect(string $path): bool
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return false;
        }

        return ! str_contains($path, '://') && ! str_contains($path, '\\');
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

        // (2026-08-25 수정, 로드맵 1·2번) 예전엔 무조건 redirect('/')였는데, "/"가 계산기에서
        // 마케팅 홈으로 바뀐 뒤로는 로그인 후 항상 홈으로 튕겨나가면서 하던 작업(궁합 입력,
        // 리포트 구매 시도 등)이 전부 날아가는 문제가 있었다. redirect()->intended()는
        // session('url.intended')가 있으면(위 redirect()에서 수동으로 채웠거나, auth
        // 미들웨어가 자동으로 채운 경우 모두) 그 경로로, 없으면 홈으로 보낸다.
        return redirect()->intended(route('home'));
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
