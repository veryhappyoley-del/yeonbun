{{--
  paragraphs 블록 — 가장 기본적인 서술형 블록(rpt-p 재사용). ChapterSpec::$schema는
  아래 둘 중 하나의 모양을 따르면 됩니다:

    { "paragraphs": ["문단1", "문단2"] }  또는  { "text": "한 문단짜리 서술" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php $paragraphs = $content['paragraphs'] ?? (isset($content['text']) ? [$content['text']] : []); @endphp
@foreach ($paragraphs as $p)
  @if (!empty($p))<p class="rpt-p">{{ $p }}</p>@endif
@endforeach
