<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>내 리포트함 — 결</title>
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
      <h1>내 리포트함</h1>
      <p>구매한 심층 리포트와 프리미엄 궁합 리포트를 여기서 다시 볼 수 있어요.</p>
    </div>
  </div>

  <div class="card">
    @if ($reports->isEmpty())
      <div class="empty-state">아직 구매한 리포트가 없어요. '나의 연애 사주'나 '궁합 보기' 결과 화면 아래에서 프리미엄 리포트를 만나보세요.</div>
    @else
      <div class="report-list">
        <div class="chat-session-list">
          @foreach ($reports as $report)
            <a class="chat-session-row" href="{{ route('reports.show', $report) }}">
              <div class="chat-session-main">
                <div class="chat-session-user">{{ $report->title ?: ($types[$report->type]['label'] ?? $report->type) }}</div>
                <div class="chat-session-preview">{{ $types[$report->type]['label'] ?? $report->type }}</div>
              </div>
              <div class="chat-session-meta">
                <span>{{ number_format($report->amount) }}원</span>
                <span>{{ $report->updated_at->format('Y.m.d') }}</span>
              </div>
            </a>
          @endforeach
        </div>
      </div>
    @endif
  </div>

</div>

@include('partials.site-bottom-nav')

</body>
</html>
