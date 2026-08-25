{{--
  전역 하단 탭바 (2026-08-24 신설, 헤더/하단탭 도입 작업) — 홈 / 사전 / 사주 / 마이(비로그인
  시 "로그인").

  화면 하단에 고정(position: fixed)해서 스크롤 중에도 항상 손 닿는 곳에 있게 한다. 데스크톱
  에서는 사이트 전체가 "폰 프레임"(body.phone-app, .wrap을 440px로 고정)으로 보이는데(app.css
  참고), 이 바도 똑같이 max-width:440px + 좌우 auto 마진으로 가운데 정렬해서 프레임 폭과
  어긋나지 않게 맞춘다.

  "사주"는 매출이 실제로 나오는 핵심 진입점이라 원형 뱃지로 살짝 튀어나오게 강조했다(참고로
  주신 경쟁사 화면의 "가운데 탭 강조" 아이디어는 가져오되, 색은 사이트 결 그대로 seal 톤).

  활성 탭 판정 (2026-08-25 정리): 처음 만들 때는 "/"(home 라우트)가 홈 화면이자 계산기
  화면을 겸해서 ?tab= 파라미터 유무로 홈/사주를 억지로 구분했는데, 이제 "/"는 진짜 홈
  랜딩페이지(home.blade.php)이고 계산기는 /calculator(calculator.index)로 분리돼서 그냥
  routeIs()만으로 판정하면 된다.
--}}
@php
  $navHomeActive = request()->routeIs('home');
  $navSaguActive = request()->routeIs('sagu.index') || request()->routeIs('calculator.index');
  $navDictActive = request()->routeIs('dictionary.index');
  $navMyActive = request()->routeIs('my.index');
@endphp
<nav class="site-bottom-nav" aria-label="주요 메뉴">
  <a class="site-bottom-nav-item @if ($navHomeActive) active @endif" href="{{ route('home') }}">
    <svg class="site-bottom-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M4 11.5 12 4l8 7.5" />
      <path d="M5.5 10v9a1 1 0 0 0 1 1H9v-6h6v6h2.5a1 1 0 0 0 1-1v-9" />
    </svg>
    <span class="site-bottom-nav-label">홈</span>
  </a>

  <a class="site-bottom-nav-item @if ($navDictActive) active @endif" href="{{ route('dictionary.index') }}">
    <svg class="site-bottom-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 6.5c-1.3-1-3.2-1.5-5-1.5-1 0-1.5.15-1.5.5v12.5c0 .3.4.5 1 .5 1.7 0 3.6.5 5 1.5" />
      <path d="M12 6.5c1.3-1 3.2-1.5 5-1.5 1 0 1.5.15 1.5.5v12.5c0 .3-.4.5-1 .5-1.7 0-3.6.5-5 1.5V6.5Z" />
    </svg>
    <span class="site-bottom-nav-label">사전</span>
  </a>

  <a class="site-bottom-nav-item site-bottom-nav-item--primary @if ($navSaguActive) active @endif" href="{{ route('sagu.index') }}">
    <span class="site-bottom-nav-badge" aria-hidden="true">사</span>
    <span class="site-bottom-nav-label">사주</span>
  </a>

  <a class="site-bottom-nav-item @if ($navMyActive) active @endif" href="{{ route('my.index') }}">
    <svg class="site-bottom-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="8.2" r="3.2" />
      <path d="M5.2 19.5c0-3.6 3-5.8 6.8-5.8s6.8 2.2 6.8 5.8" />
    </svg>
    <span class="site-bottom-nav-label">@auth 마이 @else 로그인 @endauth</span>
  </a>
</nav>
