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
--}}
@php $stages = $content['stages'] ?? []; @endphp
@if ($stages)
  <div class="rpt-os-grid">
    @foreach ($stages as $stage)
      @if (!empty($stage['title']))
        <div class="rpt-os-card">
          <div class="rpt-os-title">{{ $stage['title'] }}</div>
          @foreach (($stage['lines'] ?? []) as $line)
            @if (!empty($line['text']))
              <div class="rpt-os-line"><span>{{ $line['label'] ?? '' }}</span>{{ $line['text'] }}</div>
            @endif
          @endforeach
        </div>
      @endif
    @endforeach
  </div>
@endif
