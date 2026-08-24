{{--
  advice_cards 블록 — 기존 RELATIONSHIP ADVICE 섹션의 rpt-advice-card를 재사용합니다.
  ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    {
      "items": [
        { "label": "선택: 없으면 순번(조언 1)으로 대체", "situation": "", "problem": "", "action": "" }
      ]
    }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) items가 배열이 아니거나 원소가 객체가 아닌 경우를
  대비한 방어 코드 — paragraphs.blade.php와 같은 이유.
--}}
@php $items = is_array($content['items'] ?? null) ? $content['items'] : []; @endphp
@foreach ($items as $i => $item)
  @continue(! is_array($item))
  <div class="rpt-advice-card">
    <div class="rpt-advice-num">{{ $item['label'] ?? ('조언 '.($i + 1)) }}</div>
    @if (!empty($item['situation']))<div class="rpt-os-line"><span>상황</span>{{ $item['situation'] }}</div>@endif
    @if (!empty($item['problem']))<div class="rpt-os-line"><span>문제</span>{{ $item['problem'] }}</div>@endif
    @if (!empty($item['action']))<div class="rpt-os-line"><span>추천 행동</span>{{ $item['action'] }}</div>@endif
  </div>
@endforeach
