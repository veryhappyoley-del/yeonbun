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
  {{-- (2026-08-26 수정) "PDF로 저장"이 브라우저 인쇄 대화상자(Ctrl+P)를 띄우던 방식에서
       실제 .pdf 파일을 바로 다운로드하는 방식으로 바뀌었다 — 사용자 피드백: "인쇄 방식이
       뭔가 어색하다"(인쇄 대화상자가 뜨고 사용자가 그 안에서 다시 "PDF로 저장"을 골라야
       하는 2단계 과정이 "다운로드" 버튼의 기대와 안 맞았음). public/js/report-pdf.js가
       html2canvas(이미 saju.blade.php의 캐릭터 카드 공유 기능에서 쓰던 라이브러리라 이미
       검증된 CDN 의존성)로 화면에 실제로 렌더링된 챕터/섹션을 이미지로 캡처한 뒤 jsPDF로
       A4 페이지에 이어 붙여서 pdf.save()로 바로 다운로드시킨다. 서버에서 직접 PDF를
       만들지 않는(dompdf 같은 라이브러리를 안 쓰는) 이유는 여전히 유효하다 — 한글 폰트를
       서버가 별도로 심어야 하는 위험(로드맵 문서 "PDF 저장 + 리포트함 리디자인" 절 참고)을
       피하고, 브라우저에 이미 로드된 실제 폰트(Song Myung/Gowun Dodum)로 캡처하기
       때문에 깨질 위험이 없다. 리포트함 목록의 "PDF 저장" 버튼은 이 페이지로 ?print=1을
       붙여 들어오는데(파라미터 이름은 예전 그대로 유지 — 내부 구현 세부사항이라 사용자
       화면엔 안 보임), 아래 스크립트가 콘텐츠 준비 완료 상태일 때만 자동으로 다운로드를
       시작한다. 텍스트가 이미지로 캡처되는 방식이라(공유 카드 기능과 동일한 트레이드오프)
       PDF 안 글자는 선택/복사는 안 되지만, 화면에 보이는 모습 그대로 정확하게 나온다. --}}
  <div class="topbar no-print" style="justify-content:space-between;">
    <a class="chip-link" href="{{ route('reports.index') }}">&larr; 내 리포트함으로</a>
    <button type="button" class="chip-link" id="report-pdf-btn" style="cursor:pointer;">
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-1px; margin-right:3px;"><path d="M12 3v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 21h14"></path></svg>
      PDF로 저장
    </button>
  </div>

  <div class="card" style="margin-top:18px;" id="report-pdf-root">
    <div id="report-pdf-header">
      <h2>{{ $report->title ?: ($type['label'] ?? '리포트') }}</h2>

      <div class="report-meta">
        <span>{{ $type['label'] ?? $report->type }}</span>
        <span>{{ number_format($report->amount) }}원 결제완료</span>
        <span>{{ $report->updated_at->format('Y.m.d H:i') }}</span>
      </div>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="{{ asset('js/report-pdf.js') }}"></script>
<script>
  // (2026-08-26 수정) "PDF로 저장" = 실제 .pdf 파일 다운로드(자세한 배경은 위 주석 참고).
  // 버튼을 직접 눌렀을 때는 언제나 다운로드를 시작하고, 리포트함 목록에서 ?print=1 을
  // 달고 곧장 들어온 경우엔(printReady일 때만) 사용자가 버튼을 한 번 더 누르지 않아도
  // 되게 자동으로 다운로드를 시작한다.
  @php
    $pdfFilenameBase = $report->title ?: ($type['label'] ?? '리포트');
    $pdfFilenameSafe = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $pdfFilenameBase)) ?: '연록_리포트';
  @endphp
  var reportPdfFilename = @json($pdfFilenameSafe.'.pdf');

  document.getElementById('report-pdf-btn').addEventListener('click', function () {
    window.YeonbunReportPdf.download({ button: this, filename: reportPdfFilename });
  });
  @if (($printReady ?? false) && request()->boolean('print'))
    window.addEventListener('load', function () {
      setTimeout(function () {
        window.YeonbunReportPdf.download({
          button: document.getElementById('report-pdf-btn'),
          filename: reportPdfFilename
        });
      }, 300);
    });
  @endif
</script>

</body>
</html>
