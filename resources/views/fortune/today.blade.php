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

<div class="wrap">

  @include('partials.site-header')

  @if ($fortune)
    {{-- (2026-09-28 개편) 전달본 상단바 + 날짜 아이브로. 내용은 그대로다. --}}
    {{-- (2026-09-28) 본문에 이미 화면 주제목 h1이 있어서, 상단바 제목은 heading으로 올리지 않는다. --}}
    @include('partials.app-topbar', ['titleTag' => 'p', 'title' => '오늘의 운세', 'back' => route('fortune.index')])
    <div class="section-copy compact">
      <span class="eyebrow">{{ $fortune->fortune_date->format('Y.m.d') }}</span>
      <h1>{{ $fortune->content['headline'] ?? '오늘의 운세' }}</h1>
    </div>

    <div class="card">
      @foreach (($fortune->content['paragraphs'] ?? []) as $paragraph)
        <p class="rpt-p">{{ $paragraph }}</p>
      @endforeach

      {{-- (2026-09-19 개편) 폼용 .field-row를 빌려 쓰던 걸 지표 전용 카드 3칸으로 바꿨다.
           값과 표시 항목은 그대로다. --}}
      <div class="fortune-facts">
        <div class="fortune-fact">
          <div class="fortune-fact-label">오늘의 색</div>
          <div class="fortune-fact-value">{{ $fortune->content['lucky_color'] ?? '-' }}</div>
        </div>
        <div class="fortune-fact">
          <div class="fortune-fact-label">오늘의 시간</div>
          <div class="fortune-fact-value">{{ $fortune->content['lucky_time'] ?? '-' }}</div>
        </div>
        <div class="fortune-fact">
          <div class="fortune-fact-label">오늘의 키워드</div>
          <div class="fortune-fact-value">{{ $fortune->content['keyword'] ?? '-' }}</div>
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
