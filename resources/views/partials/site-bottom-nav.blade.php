{{--
  전역 하단 탭바 — UI 전달본 .bottom-nav (2026-09-28 개편).

  전달본은 4칸 그리드(오늘/관계/분석/기록)를 예시로 쓰지만, 그 이름을 그대로 베끼지 않고
  이 서비스의 실제 사용자 흐름에 맞춰 네 칸을 정했다.

    홈(/)          서비스 소개와 시작점
    사주(/sagu)    운세·분석 종목 고르기 — 매출이 나오는 핵심 진입점
    기록(/reports) 구매한 리포트 다시 보기
    마이(/my)      코인·구독·계정

  예전 탭에 있던 "사전"은 네 칸 구성에 맞추며 뺐지만, 마이페이지와 푸터에서 계속 갈 수
  있어 접근 경로는 그대로 유지된다(경로/라우트 자체는 변경 없음).
  아이콘은 전달본 자산(assets/icons.svg)의 home/relation/chart/record 모양을 쓴다.
--}}
@php
  $navHomeActive = request()->routeIs('home');
  $navSaguActive = request()->routeIs('sagu.index') || request()->routeIs('calculator.index');
  $navRecordActive = request()->routeIs('reports.index') || request()->routeIs('reports.show');
  $navMyActive = request()->routeIs('my.index') || request()->routeIs('billing.index')
      || request()->routeIs('billing.complete') || request()->routeIs('fortune.index')
      || request()->routeIs('fortune.today') || request()->routeIs('dictionary.index')
      || request()->routeIs('privacy.index');
@endphp
<nav class="site-bottom-nav" aria-label="주요 메뉴">
  <a class="site-bottom-nav-item @if ($navHomeActive) active @endif" href="{{ route('home') }}"
     @if ($navHomeActive) aria-current="page" @endif>
    @include('partials.icon', ['name' => 'home'])
    <span class="site-bottom-nav-label">홈</span>
  </a>
  <a class="site-bottom-nav-item @if ($navSaguActive) active @endif" href="{{ route('sagu.index') }}"
     @if ($navSaguActive) aria-current="page" @endif>
    @include('partials.icon', ['name' => 'relation'])
    <span class="site-bottom-nav-label">사주</span>
  </a>
  <a class="site-bottom-nav-item @if ($navRecordActive) active @endif" href="{{ route('reports.index') }}"
     @if ($navRecordActive) aria-current="page" @endif>
    @include('partials.icon', ['name' => 'record'])
    <span class="site-bottom-nav-label">기록</span>
  </a>
  <a class="site-bottom-nav-item @if ($navMyActive) active @endif" href="{{ route('my.index') }}"
     @if ($navMyActive) aria-current="page" @endif>
    @include('partials.icon', ['name' => 'user'])
    <span class="site-bottom-nav-label">@auth 마이 @else 로그인 @endauth</span>
  </a>
</nav>
