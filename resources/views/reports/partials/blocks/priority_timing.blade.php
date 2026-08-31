{{--
  priority_timing 블록 — "짝사랑 탈출" 리포트의 "언제 움직여야 하는가" 챕터 전용
  (2026-08-31 신설). ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    {
      "picks": [
        { "period_label": "", "reason": "", "action": "" }
      ],
      "overall_note": ""
    }

  period_label은 AI가 지어낸 값이 아니라 반드시 input.timingCandidates(대운/세운을
  실제로 계산해서 만든 후보 목록, public/js/luck-cycle.js 참고)에 있던 값 중 하나를
  그대로 옮겨 적은 것이어야 합니다 — App\ReportTypes\Definitions\UnrequitedLoveReportType
  의 moving_timing 챕터 promptGuidance가 이 규칙을 강제합니다. 이 블록 자체는 화면에
  순서(1순위/2순위/3순위)와 함께 그 값을 보여주기만 합니다.

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $picks = is_array($content['picks'] ?? null) ? $content['picks'] : [];
  $rankLabels = ['1순위', '2순위', '3순위', '4순위', '5순위'];
@endphp
@foreach ($picks as $i => $pick)
  @continue(! is_array($pick))
  @continue(empty($pick['period_label']))
  <div class="rpt-timing-card">
    <div class="rpt-timing-head">
      <span class="rpt-timing-rank">{{ $rankLabels[$i] ?? (($i + 1).'순위') }}</span>
      <span class="rpt-timing-period">{{ $pick['period_label'] }}</span>
    </div>
    @if (!empty($pick['reason']))<div class="rpt-os-line"><span>이유</span>{{ $pick['reason'] }}</div>@endif
    @if (!empty($pick['action']))<div class="rpt-os-line"><span>추천 행동</span>{{ $pick['action'] }}</div>@endif
  </div>
@endforeach
@if (!empty($content['overall_note']) && is_scalar($content['overall_note']))
  <p class="rpt-p" style="margin-top:10px;">{{ $content['overall_note'] }}</p>
@endif
