{{--
  score_bars 블록 — 기존 LOVE SCORE/COMPATIBILITY에서 쓰던 rpt-score-list 시각 언어를
  그대로 재사용합니다. 이 블록을 쓰는 챕터의 ChapterSpec::$schema는 반드시 아래 모양을
  따라야 합니다(순서가 곧 표시 순서이므로, 스키마의 키 순서를 그대로 유지):

    {
      "scores": {
        "임의_키": { "label": "화면에 보일 라벨", "value": 0~100 }
      },
      "scores_note": "선택: 점수 아래에 붙는 짧은 설명"
    }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php $scores = $content['scores'] ?? []; @endphp
@if ($scores)
  <div class="rpt-score-list">
    @foreach ($scores as $s)
      @if (isset($s['label'], $s['value']))
        <div class="rpt-score-row">
          <span class="rpt-score-label">{{ $s['label'] }}</span>
          <div class="rpt-score-track"><div class="rpt-score-fill" style="width:{{ (int) $s['value'] }}%"></div></div>
          <span class="rpt-score-value">{{ (int) $s['value'] }}</span>
        </div>
      @endif
    @endforeach
  </div>
  @if (!empty($content['scores_note']))
    <p class="rpt-p" style="margin-top:10px;">{{ $content['scores_note'] }}</p>
  @endif
@endif
