{{--
  label_groups 블록 — (2026-09-08 신설) "재물운"/"커리어운" 리포트의 결론 챕터처럼,
  "핵심 강점 5가지 / 적합한 직무 5가지 / 피해야 할 환경 3가지"처럼 라벨이 있는 짧은
  항목 리스트를 여러 묶음(그룹) 보여줘야 할 때 쓴다. keyword_chips가 "키워드 하나만"
  다루는 것과 달리, 이 블록은 "여러 그룹 × 그룹마다 여러 항목"을 한 번에 보여준다.
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 한다:

    { "groups": [ { "label": "핵심 강점", "items": ["", "", "..."] } ] }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (방어 코드) groups/items가 배열이 아니거나 원소가 기대한 모양이 아닌 경우를 대비 —
  paragraphs.blade.php와 같은 이유.
--}}
@php
  $groups = is_array($content['groups'] ?? null) ? $content['groups'] : [];
  $chipVariants = ['seal', 'indigo', 'gold'];
@endphp
@if (!empty($groups))
  <div class="rpt-label-groups">
    @foreach ($groups as $gi => $group)
      @continue(! is_array($group))
      @php
        $items = is_array($group['items'] ?? null) ? $group['items'] : [];
        $items = array_values(array_filter($items, fn ($i) => is_scalar($i) && $i !== ''));
      @endphp
      @if (!empty($group['label']) && !empty($items))
        <div class="rpt-label-group">
          <div class="rpt-label-group-title">{{ $group['label'] }}</div>
          <div class="rpt-chip-row">
            @foreach ($items as $item)
              <span class="rpt-chip rpt-chip--{{ $chipVariants[$gi % 3] }}">{{ $item }}</span>
            @endforeach
          </div>
        </div>
      @endif
    @endforeach
  </div>
@endif
