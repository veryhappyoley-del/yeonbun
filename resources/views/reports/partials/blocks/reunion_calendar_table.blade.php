{{--
  reunion_calendar_table 블록 — "다시, 우리"(재회 전략) 리포트의 "재회 타이밍 캘린더"
  챕터 전용(2026-08-31 신설). concern_answer 블록과 같은 이유로 AI 콘텐츠($content)가
  아니라 $report->input을 직접 읽습니다 — chapter-reader.blade.php의 @include는 Blade
  기본 동작상 호출 시점의 전체 변수 스코프를 그대로 넘기므로 $report에 별도 전달 없이
  접근 가능합니다.

  12개월 전체 표는 public/js/luck-cycle.js의 monthlyCalendar()가 실제로 계산한
  대운/세운 점수를 별점(1~5)·추천 행동으로 정리한 값이라, 여기서는 그 값을 있는 그대로
  보여주기만 합니다(AI가 숫자나 등급을 지어낼 여지 자체가 없음) — 상위 3개 시기에 대한
  AI의 코멘트는 이 챕터의 다른 블록(priority_timing, $content['picks'] 사용)이 담당합니다.

  기대 모양: $report->input['monthlyCalendar'] = [
    { "year": 0, "month": 0, "periodLabel": "", "stars": 1~5, "action": "" }, ...
  ] — public/js/luck-cycle.js의 monthlyCalendar().months를 public/js/reports.js의
  buildReunionInput()이 키 이름 변경 없이 그대로 전달한다(JS 객체 키 그대로 JSON
  인코딩되므로 camelCase 그대로 유지).
--}}
@php
  $input = (isset($report) && is_array($report->input ?? null)) ? $report->input : [];
  $months = is_array($input['monthlyCalendar'] ?? null) ? $input['monthlyCalendar'] : [];
@endphp
@if (!empty($months))
  <div class="rpt-calendar">
    @foreach ($months as $row)
      @continue(! is_array($row) || empty($row['periodLabel']))
      @php $stars = max(1, min(5, (int) ($row['stars'] ?? 0))); @endphp
      <div class="rpt-calendar-row">
        <span class="rpt-calendar-period">{{ $row['periodLabel'] }}</span>
        <span class="rpt-calendar-stars" aria-hidden="true">{{ str_repeat('⭐', $stars).str_repeat('☆', 5 - $stars) }}</span>
        @if (!empty($row['action']))<span class="rpt-calendar-action">{{ $row['action'] }}</span>@endif
      </div>
    @endforeach
  </div>
@endif
