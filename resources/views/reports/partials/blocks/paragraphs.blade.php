{{--
  paragraphs 블록 — 가장 기본적인 서술형 블록(rpt-p 재사용). ChapterSpec::$schema는
  아래 둘 중 하나의 모양을 따르면 됩니다:

    { "paragraphs": ["문단1", "문단2"] }  또는  { "text": "한 문단짜리 서술" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) 예전(Tool Use 전환 이전) 방식으로 생성된 일부 레거시
  챕터는 paragraphs가 배열이 아니라 문자열 하나로 잘못 저장된 경우가 있어
  foreach()가 크래시했습니다. is_array가 아니면 문자열 하나짜리 배열로 감싸서
  방어합니다 — 근본 수정(ChapterGenerator의 타입 검증 + chapters:revalidate 명령)과
  별개로, 뷰 단에서도 안전망을 둡니다.
--}}
@php
  $paragraphs = $content['paragraphs'] ?? (isset($content['text']) ? [$content['text']] : []);

  if (! is_array($paragraphs)) {
      $paragraphs = [$paragraphs];
  }
@endphp
@foreach ($paragraphs as $p)
  @if (!empty($p) && is_scalar($p))<p class="rpt-p">{{ $p }}</p>@endif
@endforeach
