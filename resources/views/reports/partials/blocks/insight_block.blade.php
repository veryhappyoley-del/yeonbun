{{--
  insight_block 블록 — (2026-09-08 신설) "재물운"/"커리어운" 리포트 대부분의 챕터가
  쓰는 기본 골격. 사용자가 요청한 작성 순서(제목[챕터 제목으로 이미 표시]/한 줄 요약/
  상세 해석/명리학적 근거/현실적인 활용법)를 그대로 구조화한 것 — paragraphs 블록처럼
  뭉뚱그려 쓰지 않고 "왜 그렇게 판단했는지"(basis)와 "그래서 어떻게 하면 좋은지"
  (application)를 항상 분리해서 보여준다. ChapterSpec::$schema는 반드시 아래 모양을
  따라야 한다:

    {
      "summary": "한 줄 요약",
      "detail": ["상세 해석 문단1", "문단2(선택)"],
      "basis": "이 판단의 명리학적 근거",
      "application": "현실에서 활용하는 방법"
    }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (방어 코드) 각 필드가 기대한 타입이 아닌 경우를 대비 — paragraphs.blade.php와 같은 이유.
--}}
@php
  $summary = is_string($content['summary'] ?? null) ? trim($content['summary']) : '';
  $detail = $content['detail'] ?? [];
  if (! is_array($detail)) {
      $detail = is_scalar($detail) ? [$detail] : [];
  }
  $basis = is_string($content['basis'] ?? null) ? trim($content['basis']) : '';
  $application = is_string($content['application'] ?? null) ? trim($content['application']) : '';
@endphp
@if ($summary !== '')
  <div class="rpt-quote">{{ $summary }}</div>
@endif
@foreach ($detail as $p)
  @continue(! is_scalar($p))
  @if ($p !== '')<p class="rpt-p">{{ $p }}</p>@endif
@endforeach
@if ($basis !== '')
  <div class="rpt-os-line"><span>명리학적 근거</span>{{ $basis }}</div>
@endif
@if ($application !== '')
  <div class="rpt-os-line"><span>이렇게 활용해보세요</span>{{ $application }}</div>
@endif
