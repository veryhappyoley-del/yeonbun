<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>오늘의 운세 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  @if ($fortune)
    <div class="hero">
      <div class="hero-text">
        <p class="sub">{{ $fortune->fortune_date->format('Y년 n월 j일') }}</p>
        <h1>{{ $fortune->content['headline'] ?? '오늘의 운세' }}</h1>
      </div>
    </div>

    <div class="card">
      @foreach (($fortune->content['paragraphs'] ?? []) as $paragraph)
        <p class="rpt-p">{{ $paragraph }}</p>
      @endforeach

      <div class="field-row" style="margin-top:16px; text-align:center;">
        <div>
          <div class="hint">오늘의 색</div>
          <strong>{{ $fortune->content['lucky_color'] ?? '-' }}</strong>
        </div>
        <div>
          <div class="hint">오늘의 시간</div>
          <strong>{{ $fortune->content['lucky_time'] ?? '-' }}</strong>
        </div>
        <div>
          <div class="hint">오늘의 키워드</div>
          <strong>{{ $fortune->content['keyword'] ?? '-' }}</strong>
        </div>
      </div>
    </div>
  @else
    <div class="card" style="text-align:center;">
      <h2>아직 준비된 운세가 없어요</h2>
      <div class="hint">구독을 시작했다면 다음 날 새벽에 첫 운세가 도착해요.</div>
    </div>
  @endif

  <a class="chip-link" href="{{ route('fortune.index') }}">구독 관리로 돌아가기 &rarr;</a>

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

</body>
</html>
