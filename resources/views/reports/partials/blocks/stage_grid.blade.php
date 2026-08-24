{{--
  stage_grid 블록 — 기존 LOVE OS/PARTNER'S VIEW에서 쓰던 rpt-os-grid 카드 그리드를
  그대로 재사용합니다. ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    {
      "stages": {
        "임의_키": {
          "title": "카드 제목",
          "lines": [ { "label": "감정", "text": "..." }, { "label": "행동", "text": "..." } ]
        }
      }
    }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.

  (2026-08-24 방어 코드 추가) stages/lines가 배열이 아니거나 원소가 객체가 아닌
  경우를 대비한 방어 코드 — paragraphs.blade.php와 같은 이유.
--}}
@php $stages = is_array($content['stages'] ?? null) ? $content['stages'] : []; @endphp
@if ($stages)
  <div class="rpt-os-grid">
    @foreach ($stages as $stage)
      @continue(! is_array($stage))
      @if (!empty($stage['title']))
        <div class="rpt-os-card">
          <div class="rpt-os-title">{{ $stage['title'] }}</div>
          @php $lines = is_array($stage['lines'] ?? null) ? $stage['lines'] : []; @endphp
          @foreach ($lines as $line)
            @continue(! is_array($line))
            @if (!empty($line['text']))
              <div class="rpt-os-line"><span>{{ $line['label'] ?? '' }}</span>{{ $line['text'] }}</div>
            @endif
          @endforeach
        </div>
      @endif
    @endforeach
  </div>
@endif
