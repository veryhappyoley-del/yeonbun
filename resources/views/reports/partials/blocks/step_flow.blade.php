{{--
  step_flow 블록 — 기존 RECURRING PATTERN 섹션의 rpt-pattern-flow를 재사용합니다.
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "steps": ["", "", "..."], "key_point": "선택: 마지막에 강조할 한 줄" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@if (!empty($content['steps']))
  <div class="rpt-pattern-flow">
    @foreach ($content['steps'] as $i => $step)
      <div class="rpt-pattern-step"><span class="rpt-pattern-num">{{ $i + 1 }}</span>{{ $step }}</div>
    @endforeach
  </div>
  @if (!empty($content['key_point']))
    <div class="rpt-quote">💡 {{ $content['key_point'] }}</div>
  @endif
@endif
