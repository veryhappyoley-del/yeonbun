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

    @if ($report->type === 'compat')
      @if ($report->content)
        <div class="report-body">
          {!! $report->content !!}
        </div>
      @else
        @include('reports.partials.pending', ['report' => $report])
      @endif
    @else
      @if ($data && empty($data['data_conflict']))
        @include('reports.partials.single-report', ['data' => $data, 'input' => $report->input ?? []])
      @elseif ($data && ! empty($data['data_conflict']))
        <div class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
          입력된 사주 정보에 확인이 필요한 부분이 있어서 이번 분석은 신뢰도가 낮게 나왔어요. 이미 결제는 완료된 상태이니, 생년월일시 정보를 다시 한 번 확인한 뒤 "나의 연애 사주"를 새로 계산해서 리포트를 다시 만들어보시는 걸 추천드려요.
        </div>
      @else
        @include('reports.partials.pending', ['report' => $report])
      @endif
    @endif
  </div>

  <footer>
    이 리포트는 AI가 사주 계산 결과를 바탕으로 생성한 해석 콘텐츠로, 통계적·성향적 참고용입니다. 연애의 실제 결과를 보장하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

</body>
</html>
