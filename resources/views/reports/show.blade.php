<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $report->title ?: ($type['label'] ?? '리포트') }} — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  {{-- 전역 헤더와 별개로, 리포트함 목록으로 곧장 되돌아가는 페이지 전용 링크는 그대로 둔다. --}}
  {{-- (2026-08-25 추가, 로드맵 4번) "PDF로 저장" 버튼 — 서버에서 PDF를 직접 만들지 않고
       브라우저 인쇄 기능(Ctrl+P → "PDF로 저장")을 그대로 활용한다. 이유는 public/css/
       app.css의 @media print 블록 주석 참고(한글 폰트를 서버가 직접 심어야 하는 위험을
       피하기 위함). 리포트함 목록의 "PDF 저장" 버튼은 이 페이지로 ?print=1 을 붙여
       들어오는데, 아래 스크립트가 콘텐츠 준비 완료 상태일 때만 자동으로 인쇄창을 띄운다. --}}
  <div class="topbar no-print" style="justify-content:space-between;">
    <a class="chip-link" href="{{ route('reports.index') }}">&larr; 내 리포트함으로</a>
    <button type="button" class="chip-link" id="report-print-btn" style="cursor:pointer;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px; margin-right:3px;"><path d="M12 3v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 21h14"></path></svg>
      PDF로 저장
    </button>
  </div>

  <div class="card" style="margin-top:18px;">
    <h2>{{ $report->title ?: ($type['label'] ?? '리포트') }}</h2>

    <div class="report-meta">
      <span>{{ $type['label'] ?? $report->type }}</span>
      <span>{{ number_format($report->amount) }}원 결제완료</span>
      <span>{{ $report->updated_at->format('Y.m.d H:i') }}</span>
    </div>

    @if ($report->isChaptered())
      @if (! $reportType)
        {{-- 데이터 정합성 문제(등록되지 않은 타입)로만 발생할 수 있는 방어적 분기 —
             정상적인 흐름에서는 도달하지 않습니다. --}}
        <div class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
          리포트 정보를 불러오는 중 문제가 있었어요. 잠시 후 다시 시도해 주세요.
        </div>
      @elseif ($chaptersReady)
        @include('reports.partials.chapter-toc', ['report' => $report, 'type' => $reportType])
        @include('reports.partials.chapter-reader', ['report' => $report, 'type' => $reportType])
      @else
        @include('reports.partials.chapter-progress', ['report' => $report])
      @endif
    @elseif ($report->type === 'compat')
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
    이 리포트는 고전 명리학 이론을 폭넓게 학습한 AI가 사주 계산 결과를 바탕으로 생성한 해석 콘텐츠로, 통계적·성향적 참고용입니다. 연애의 실제 결과를 보장하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

@include('partials.site-bottom-nav')

<script>
  // (2026-08-25 추가, 로드맵 4번) "PDF로 저장" = 브라우저 인쇄 대화상자를 그대로 활용.
  // 버튼을 직접 눌렀을 때는 언제나 인쇄창을 띄우고, 리포트함 목록에서 ?print=1 을 달고
  // 곧장 들어온 경우엔(printReady일 때만) 사용자가 버튼을 한 번 더 누르지 않아도 되게
  // 자동으로 띄운다. 이미지/폰트 렌더링이 끝난 뒤 뜨도록 load 이벤트 + 약간의 지연을 둔다.
  document.getElementById('report-print-btn').addEventListener('click', function () {
    window.print();
  });
  @if (($printReady ?? false) && request()->boolean('print'))
    window.addEventListener('load', function () {
      setTimeout(function () { window.print(); }, 300);
    });
  @endif
</script>

</body>
</html>
