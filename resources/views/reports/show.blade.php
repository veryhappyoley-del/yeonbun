<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $report->title ?: ($type['label'] ?? '리포트') }} — 결</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="wrap wrap-narrow">

  <div class="topbar">
    <a class="chip-link" href="{{ route('reports.index') }}">&larr; 내 리포트함으로</a>
  </div>

  <div class="card" style="margin-top:18px;">
    <h2>{{ $report->title ?: ($type['label'] ?? '리포트') }}</h2>

    <div class="report-meta">
      <span>{{ $type['label'] ?? $report->type }}</span>
      <span>{{ number_format($report->amount) }}원 결제완료</span>
      <span>{{ $report->updated_at->format('Y.m.d H:i') }}</span>
    </div>

    @if ($report->content)
      <div class="report-body">
        {!! $report->content !!}
      </div>
    @else
      <div class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
        리포트를 생성하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 생성해 주세요.
      </div>
      <form method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center;">
        @csrf
        <button type="submit" class="btn">리포트 다시 생성하기</button>
      </form>
    @endif
  </div>

  <footer>
    이 리포트는 AI가 사주 계산 결과를 바탕으로 생성한 해석 콘텐츠로, 통계적·성향적 참고용입니다. 연애의 실제 결과를 보장하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

</body>
</html>
