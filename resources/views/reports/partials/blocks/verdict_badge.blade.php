{{--
  verdict_badge 블록 — "짝사랑 탈출" 리포트의 "이 짝사랑을 계속해도 되는가" 챕터 전용
  (2026-08-31 신설). ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "verdict": "continue|slow|reconsider", "verdict_label": "", "reason": "" }

  verdict는 continue(🟢 계속 도전)/slow(🟡 천천히 접근)/reconsider(🔴 정리 고려) 중
  정확히 하나의 문자열이어야 합니다(ChapterGenerator는 이 필드를 자유 문자열로 강제할
  뿐 enum까지는 강제하지 않으므로, App\ReportTypes\Definitions\UnrequitedLoveReportType
  의 should_continue 챕터 promptGuidance가 이 세 값 중 하나만 쓰라고 텍스트로 지침을
  줍니다). 알 수 없는 값이 오면 회색 중립 배지로 안전하게 대체합니다.

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $verdict = is_string($content['verdict'] ?? null) ? $content['verdict'] : null;
  $variants = [
    'continue' => ['emoji' => '🟢', 'class' => 'go'],
    'slow' => ['emoji' => '🟡', 'class' => 'slow'],
    'reconsider' => ['emoji' => '🔴', 'class' => 'stop'],
  ];
  $variant = $variants[$verdict] ?? ['emoji' => '⚪', 'class' => 'neutral'];
  $label = is_string($content['verdict_label'] ?? null) ? trim($content['verdict_label']) : '';
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
