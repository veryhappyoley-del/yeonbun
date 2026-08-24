{{--
  quote 블록 — 기존 rpt-quote(인용/강조 문구)를 재사용합니다. ChapterSpec::$schema는
  반드시 아래 모양을 따라야 합니다:

    { "quote": "강조해서 보여줄 한 문단", "quote_variant": "final(선택, 최종 결론류 챕터일 때)" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) quote가 문자열이 아닌 경우를 대비한 방어 코드 —
  paragraphs.blade.php와 같은 이유.
--}}
@if (!empty($content['quote']) && is_scalar($content['quote']))
  <div class="rpt-quote {{ ($content['quote_variant'] ?? null) === 'final' ? 'rpt-quote--final' : '' }}">{{ $content['quote'] }}</div>
@endif
