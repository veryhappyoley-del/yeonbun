{{--
  compare_cards 블록 — "A vs B" 형태로 대비되는 두 대상을 VS 구분선을 사이에 두고
  세로로 보여줍니다(폰 프레임 폭이 좁아 좌우 2열보다 세로 스택이 더 읽기 편함).
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다(side당 tags는 선택):

    {
      "compare": {
        "left":  { "label": "", "text": "", "tags": ["", "", "..."] },
        "right": { "label": "", "text": "", "tags": ["", "", "..."] }
      }
    }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $compare = is_array($content['compare'] ?? null) ? $content['compare'] : [];
  $left = is_array($compare['left'] ?? null) ? $compare['left'] : [];
  $right = is_array($compare['right'] ?? null) ? $compare['right'] : [];
  $leftTags = is_array($left['tags'] ?? null) ? array_values(array_filter($left['tags'], fn ($t) => is_scalar($t) && $t !== '')) : [];
  $rightTags = is_array($right['tags'] ?? null) ? array_values(array_filter($right['tags'], fn ($t) => is_scalar($t) && $t !== '')) : [];
  $hasLeft = !empty($left['text']) && is_scalar($left['text']);
  $hasRight = !empty($right['text']) && is_scalar($right['text']);
@endphp
@if ($hasLeft || $hasRight)
  <div class="rpt-compare">
    @if ($hasLeft)
      <div class="rpt-compare-side rpt-compare-side--left">
        <div class="rpt-compare-label">{{ is_scalar($left['label'] ?? null) ? $left['label'] : '' }}</div>
        <div class="rpt-compare-text">{{ $left['text'] }}</div>
        @if (!empty($leftTags))
          <div class="rpt-chip-row rpt-chip-row--tight">
            @foreach ($leftTags as $tag)
              <span class="rpt-chip rpt-chip--indigo">{{ $tag }}</span>
            @endforeach
          </div>
        @endif
      </div>
    @endif
    <div class="rpt-compare-divider">VS</div>
    @if ($hasRight)
      <div class="rpt-compare-side rpt-compare-side--right">
        <div class="rpt-compare-label">{{ is_scalar($right['label'] ?? null) ? $right['label'] : '' }}</div>
        <div class="rpt-compare-text">{{ $right['text'] }}</div>
        @if (!empty($rightTags))
          <div class="rpt-chip-row rpt-chip-row--tight">
            @foreach ($rightTags as $tag)
              <span class="rpt-chip rpt-chip--gold">{{ $tag }}</span>
            @endforeach
          </div>
        @endif
      </div>
    @endif
  </div>
@endif
