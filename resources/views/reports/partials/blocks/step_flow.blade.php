{{--
  step_flow 블록 — 기존 RECURRING PATTERN 섹션의 rpt-pattern-flow를 재사용합니다.
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "steps": ["", "", "..."], "key_point": "선택: 마지막에 강조할 한 줄" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) steps가 배열이 아닌 경우를 대비한 방어 코드 —
  paragraphs.blade.php와 같은 이유.
--}}
@php $steps = is_array($content['steps'] ?? null) ? $content['steps'] : []; @endphp
@if (!empty($steps))
  <div class="rpt-pattern-flow">
    @foreach ($steps as $i => $step)
      @continue(! is_scalar($step))
      <div class="rpt-pattern-step"><span class="rpt-pattern-num">{{ $i + 1 }}</span>{{ $step }}</div>
    @endforeach
  </div>
  @if (!empty($content['key_point']) && is_scalar($content['key_point']))
    <div class="rpt-quote">💡 {{ $content['key_point'] }}</div>
  @endif
@endif
