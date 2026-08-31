{{--
  contact_action 블록 — "다시, 우리"(재회 전략) 리포트의 "지금 연락해야 할까?" 챕터
  전용(2026-08-31 신설). verdict_badge와 완전히 같은 문법(고정된 값 집합 → 이모지/색
  매핑, verdict_label/reason은 자유 텍스트)이지만 판정이 3개가 아니라 5개라 별도 블록으로
  뒀다. ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "action": "no_contact_now|wait|light_contact|heartfelt_moment|no_contact_period",
      "action_label": "", "reason": "" }

  action은 다섯 값 중 정확히 하나의 문자열이어야 합니다(ChapterGenerator는 자유 문자열로만
  강제하므로, App\ReportTypes\Definitions\ReunionStrategyReportType의 contact_recommendation
  챕터 promptGuidance가 이 다섯 값만 쓰라고 텍스트로 지침을 줍니다). 알 수 없는 값이 오면
  회색 중립 배지로 안전하게 대체합니다.

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $action = is_string($content['action'] ?? null) ? $content['action'] : null;
  $variants = [
    'no_contact_now' => ['emoji' => '❌', 'class' => 'stop'],
    'wait' => ['emoji' => '⏳', 'class' => 'slow'],
    'light_contact' => ['emoji' => '💬', 'class' => 'info'],
    'heartfelt_moment' => ['emoji' => '❤️', 'class' => 'warm'],
    'no_contact_period' => ['emoji' => '🚫', 'class' => 'stop'],
  ];
  $variant = $variants[$action] ?? ['emoji' => '⚪', 'class' => 'neutral'];
  $label = is_string($content['action_label'] ?? null) ? trim($content['action_label']) : '';
  $reason = is_string($content['reason'] ?? null) ? trim($content['reason']) : '';
@endphp
@if ($label !== '' || $reason !== '')
  <div class="rpt-verdict rpt-verdict--{{ $variant['class'] }}">
    <div class="rpt-verdict-badge">
      <span aria-hidden="true">{{ $variant['emoji'] }}</span>
      @if ($label !== ''){{ $label }}@endif
    </div>
    @if ($reason !== '')<p class="rpt-p" style="margin-top:8px;">{{ $reason }}</p>@endif
  </div>
@endif
