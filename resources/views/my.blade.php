{{--
  마이페이지 (2026-08-24 신설, 헤더/하단탭 도입 작업) — 하단 탭바 "마이"(로그인 시) /
  "로그인"(비로그인 시)의 목적지. 코인 잔액·충전·리포트함·로그아웃을 한 곳에 모으고,
  비로그인 사용자에게는 카카오/네이버 로그인 버튼을 보여준다.

  기존엔 이 기능들이 saju.blade.php의 .topbar 한 줄에 다 흩어져 있었는데(로그인 폼+코인
  칩+리포트함 칩+이름+로그아웃 버튼), 헤더는 코인 잔액만 가볍게 보여주는 걸로 줄이고
  (partials/site-header.blade.php) 나머지는 전부 여기로 옮겼다.
--}}
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>마이페이지 — 결</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="41" text-anchor="middle" font-family="Song Myung, serif" font-size="26" fill="var(--seal)">결</text>
    </svg>
    <div class="hero-text">
      <h1>마이페이지</h1>
      @auth
        <p>{{ auth()->user()->name }}님, 오늘도 좋은 인연 되세요.</p>
      @else
        <p>로그인하면 코인 충전, 프리미엄 리포트 구매, 연애 코치 상담을 이용할 수 있어요.</p>
      @endauth
    </div>
  </div>

  @auth
    <div class="card">
      <h2>코인</h2>
      <div class="my-coin-row">
        <div class="my-coin-count">{{ auth()->user()->credits }}<span>개</span></div>
        <a class="btn" href="{{ route('billing.index') }}">충전하기</a>
      </div>
    </div>

    <div class="card">
      <h2>리포트</h2>
      <a class="chip-link" href="{{ route('reports.index') }}">내 리포트함 바로가기 &rarr;</a>
    </div>

    <div class="card">
      <h2>계정</h2>
      {{-- 카카오는 이메일 동의를 안 하면 SocialAuthController가 내부용 더미 이메일
           (provider_id@yeonbun.local)을 만들어 저장하는데, 이걸 그대로 보여주면 "내 이메일이
           왜 이상하지?" 싶을 수 있어서 그런 더미 값이면 대신 로그인 수단만 안내한다. --}}
      <div class="hint" style="margin-bottom:12px;">
        @if (auth()->user()->email && ! str_ends_with(auth()->user()->email, '@yeonbun.local'))
          {{ auth()->user()->email }}
        @else
          {{ auth()->user()->provider === 'kakao' ? '카카오' : '네이버' }} 계정으로 로그인됨
        @endif
      </div>
      <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn outline">로그아웃</button>
      </form>
    </div>
  @else
    <div class="card" style="text-align:center;">
      <h2>로그인</h2>
      <div class="hint" style="margin-bottom:16px;">카카오 또는 네이버 계정으로 간편하게 시작하세요.</div>
      <div class="login-gate-inline" style="justify-content:center;">
        {{-- (2026-08-25 추가, 로드맵 1·2번) 마이페이지에서 로그인하면 로그인 상태가 반영된
             마이페이지로 그대로 돌아오도록 redirect 파라미터를 붙인다. --}}
        <a class="social-btn kakao" href="{{ route('auth.redirect', ['provider' => 'kakao', 'redirect' => '/my']) }}">카카오로 로그인</a>
        <a class="social-btn naver" href="{{ route('auth.redirect', ['provider' => 'naver', 'redirect' => '/my']) }}">네이버로 로그인</a>
      </div>
    </div>
  @endauth

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

</body>
</html>
