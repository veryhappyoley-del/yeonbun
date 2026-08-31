<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>내 리포트함 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>내 리포트함</h1>
      <p>구매한 심층 리포트와 프리미엄 궁합 리포트를 여기서 다시 볼 수 있어요.</p>
    </div>
  </div>

  <div class="card">
    @if ($reports->isEmpty())
      <div class="empty-state">아직 구매한 리포트가 없어요. '연애의 나침반'이나 '우리의 연애온도' 결과 화면 아래에서 프리미엄 리포트를 만나보세요.</div>
    @else
      {{-- (2026-08-25 추가, 로드맵 4번) 예전엔 관리자 상담 세션 목록용 컴포넌트
           (.chat-session-row)를 그대로 재활용해서 제목/부제 두 줄짜리 밋밋한 리스트였다.
           사용자가 참고로 준 경쟁사 "나의 보관함" 화면 구조(아이콘+뱃지 → 상대방 이름 →
           날짜/영구보관 → 버튼 2개)를 그대로 가져오되, 색은 결의 palette로 새로 입힌
           전용 컴포넌트(.report-card-*)로 분리했다. --}}
      <div class="report-card-list">
        @foreach ($reports as $report)
          @php
            $label = $types[$report->type]['label'] ?? $report->type;
            $isCompat = in_array($report->type, ['compat', 'compatibility'], true);
            // (2026-08-31 추가) App\ReportTypes\Definitions\UnrequitedLoveReportType.
            $isUnrequited = $report->type === 'unrequited_love';
            // (2026-08-31 추가) App\ReportTypes\Definitions\ReunionStrategyReportType.
            $isReunion = $report->type === 'reunion_strategy';
            $title = (string) ($report->title ?? '');
            $subjectHtml = null;

            // (2026-08-31 수정) 브랜드 개편으로 title 접미사가 바뀌었다(궁합분석→연애온도,
            // 짝사랑 탈출→짝사랑의 다음 장, 연애운분석→연애의 나침반). 이미 예전 이름으로
            // 저장된 title도 계속 예쁘게 파싱되도록 정규식에 신/구 접미사를 |로 함께 둔다
            // (신규 구매는 항상 새 이름으로 저장되지만, 기존 구매 고객의 title은 그대로다).
            if ($isCompat && preg_match('/^(.+?)\s*×\s*(.+?)\s*(?:궁합분석|연애온도)$/u', $title, $m)) {
                $subjectHtml = e(trim($m[1])).' <span class="report-card-heart">♥</span> '.e(trim($m[2]));
            } elseif ($isUnrequited && preg_match('/^(.+?)님의\s*(.+?)\s*(?:짝사랑 탈출|짝사랑의 다음 장)$/u', $title, $m)) {
                $subjectHtml = e(trim($m[1])).' <span class="report-card-heart">♥</span> '.e(trim($m[2]));
            } elseif ($isReunion && preg_match('/^(.+?)\s*×\s*(.+?)\s*다시, 우리$/u', $title, $m)) {
                $subjectHtml = e(trim($m[1])).' <span class="report-card-heart">♥</span> '.e(trim($m[2]));
            } elseif (! $isCompat && ! $isUnrequited && ! $isReunion && preg_match('/^(.+?)님의\s*(?:연애운분석|연애의 나침반)$/u', $title, $m)) {
                $subjectHtml = e(trim($m[1])).'님';
            }

            // 위 패턴에 안 맞는 경우(예전 형식 title, 또는 이름 없이 산 경우)는 원래
            // title이나 상품명을 그대로 보여준다(항상 이스케이프됨 — e()로 감싸거나 {{ }}).
            $subjectHtml = $subjectHtml ?: e($title !== '' ? $title : $label);
          @endphp
          <div class="report-card">
            <div class="report-card-top">
              <div class="report-card-icon" aria-hidden="true">{{ $isCompat ? '💞' : ($isUnrequited ? '💔' : ($isReunion ? '🔄' : '💘')) }}</div>
              <span class="badge {{ $isCompat ? 'indigo' : ($isUnrequited ? 'gold' : ($isReunion ? 'water' : 'seal')) }}">{{ $label }}</span>
            </div>
            <div class="report-card-subject">{!! $subjectHtml !!}</div>
            <div class="report-card-meta">
              <span>{{ $report->updated_at->format('Y.m.d') }} 분석</span>
              <span class="report-card-dot">·</span>
              <span>영구 보관</span>
            </div>
            <div class="report-card-actions">
              <a class="btn outline" href="{{ route('reports.show', $report) }}">리포트 보기</a>
              <a class="btn" href="{{ route('reports.show', ['report' => $report, 'print' => 1]) }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="m7 11 5 5 5-5"></path><path d="M5 21h14"></path></svg>
                PDF 저장
              </a>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

</div>

@include('partials.site-bottom-nav')

</body>
</html>
