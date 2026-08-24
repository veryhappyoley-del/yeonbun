{{--
  strength_weakness 블록 — 기존 STRENGTH/WEAKNESS 섹션의 rpt-sw-card를 재사용합니다.
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "items": [ { "strength": "", "escalation": "", "weakness": "" } ] }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) items가 배열이 아니거나 원소가 객체가 아닌 경우를
  대비한 방어 코드 — paragraphs.blade.php와 같은 이유.
--}}
@php $items = is_array($content['items'] ?? null) ? $content['items'] : []; @endphp
@foreach ($items as $item)
  @continue(! is_array($item))
  <div class="rpt-sw-card">
    @if (!empty($item['strength']))<div class="rpt-sw-line rpt-sw-strength"><span>강점</span>{{ $item['strength'] }}</div>@endif
    @if (!empty($item['escalation']))<div class="rpt-sw-line rpt-sw-escalation"><span>과도해지면</span>{{ $item['escalation'] }}</div>@endif
    @if (!empty($item['weakness']))<div class="rpt-sw-line rpt-sw-weakness"><span>약점</span>{{ $item['weakness'] }}</div>@endif
  </div>
@endforeach
