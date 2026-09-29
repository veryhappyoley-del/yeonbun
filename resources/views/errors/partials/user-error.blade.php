{{--
  사용자용 오류 화면 공통 틀 (2026-09-28 신설) — UI 전달본의 오류·재시도 화면.
  errors/{code}.blade.php 들이 문구만 바꿔서 이 틀을 함께 씁니다.

  $code, $title, $desc(HTML 허용), $primaryLabel, $primaryHref(null이면 "다시 시도"),
  $showRetry(뒤로가기/새로고침 버튼 노출 여부)
--}}
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>{{ $title }} — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app">

<div class="wrap">
  {{-- (2026-09-28) 아래 center-state의 오류 제목이 이 화면의 h1이라, 상단바는 heading으로 올리지 않는다. --}}
  @include('partials.app-topbar', ['titleTag' => 'p', 'title' => '연록', 'back' => route('home'), 'backLabel' => '홈으로'])

  <div class="center-state">
    <img src="{{ asset('img/handoff/error-state.svg') }}" alt="" aria-hidden="true">
    <span class="eyebrow">오류 {{ $code }}</span>
    <h1>{{ $title }}</h1>
    <p>{!! $desc !!}</p>

    @if ($primaryHref)
      <a class="btn narrow" href="{{ $primaryHref }}">{{ $primaryLabel }}</a>
    @else
      {{-- 새로고침으로 같은 요청을 다시 시도한다(브라우저 기본 동작이라 JS 의존 없음). --}}
      <a class="btn narrow" href="{{ url()->current() }}" rel="nofollow">{{ $primaryLabel }}</a>
    @endif

    @if ($showRetry)
      <a class="text-button" href="{{ route('home') }}">홈으로 이동</a>
    @else
      <a class="text-button" href="{{ route('my.index') }}">마이페이지로 이동</a>
    @endif

    <small>문제가 계속되면 {{ config('business.email', 'help@yeonrok.kr') }} 으로 알려주세요.</small>
  </div>

  @include('partials.business-footer')
</div>

</body>
</html>
