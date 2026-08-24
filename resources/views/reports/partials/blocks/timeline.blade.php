{{--
  timeline 블록 — step_flow와 완전히 같은 데이터 모양(steps/key_point)을 세로 타임라인
  (연결선 + 오행 색상을 순서대로 순환하는 번호 노드)으로 그립니다. "반복되는 패턴의
  흐름"처럼 순서가 있는 내용을 단순 번호 목록보다 시각적으로 명확하게 보여줍니다.
  ChapterSpec::$schema는 step_flow와 완전히 동일합니다(스키마/프롬프트 변경 없이
  blocks만 바꿔서 재사용 가능):

    { "steps": ["", "", "..."], "key_point": "선택: 마지막에 강조할 한 줄" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $steps = is_array($content['steps'] ?? null) ? $content['steps'] : [];
  $steps = array_values(array_filter($steps, fn ($s) => is_scalar($s) && $s !== ''));
  $elementCycle = ['wood', 'fire', 'earth', 'metal', 'water'];
@endphp
@if (!empty($steps))
  <div class="rpt-timeline">
    @foreach ($steps as $i => $step)
      <div class="rpt-timeline-step">
        <span class="rpt-timeline-num" style="background: var(--{{ $elementCycle[$i % 5] }})">{{ $i + 1 }}</span>
        <div class="rpt-timeline-text">{{ $step }}</div>
      </div>
    @endforeach
  </div>
  @if (!empty($content['key_point']) && is_scalar($content['key_point']))
    <div class="rpt-timeline-key-point">💡 <span>{{ $content['key_point'] }}</span></div>
  @endif
@endif
