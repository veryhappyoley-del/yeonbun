<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>관리자 대시보드 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body>

<div class="wrap">

  <div class="topbar">
    <a class="chip-link" href="{{ route('home') }}">&larr; 연록으로 돌아가기</a>
  </div>

  <div class="hero">
    <div class="hero-text">
      <h1>관리자 대시보드</h1>
      <p>{{ $stDate }} ~ {{ $edDate }} 기준으로 방문자, 전환율, 매출을 한눈에 봐요.</p>
    </div>
  </div>

  <div class="admin-tabs">
    <a href="{{ route('admin.dashboard') }}" class="admin-tab active">대시보드</a>
    <a href="{{ route('admin.chats') }}" class="admin-tab">상담 내용</a>
  </div>

  <form method="GET" action="{{ route('admin.dashboard') }}" class="filter-row">
    <div class="filter-field">
      <label for="st_date">시작일</label>
      <input type="date" id="st_date" name="st_date" value="{{ $stDate }}" max="{{ $edDate }}">
    </div>
    <div class="filter-field">
      <label for="ed_date">종료일</label>
      <input type="date" id="ed_date" name="ed_date" value="{{ $edDate }}" min="{{ $stDate }}">
    </div>
    <button type="submit" class="filter-submit">조회</button>
    <a href="{{ route('admin.dashboard') }}" class="filter-reset">최근 7일로 초기화</a>
  </form>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-label">누적 방문자</div>
      <div class="stat-value">{{ number_format($totalVisitors) }}<span>명</span></div>
      <div class="stat-sub">페이지뷰 {{ number_format($totalPageViews) }}회</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">가입자</div>
      <div class="stat-value">{{ number_format($totalUsers) }}<span>명</span></div>
      <div class="stat-sub">방문자 → 가입 {{ $visitorToSignup }}%</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">결제 고객</div>
      <div class="stat-value">{{ number_format($payingUsers) }}<span>명</span></div>
      <div class="stat-sub">가입 → 결제 {{ $signupToPaid }}% · 방문 → 결제 {{ $visitorToPaid }}%</div>
    </div>
    <div class="stat-card highlight">
      <div class="stat-label">누적 매출</div>
      <div class="stat-value">{{ number_format($totalRevenue) }}<span>원</span></div>
      <div class="stat-sub">결제 건수 {{ number_format($totalPayments) }}건</div>
    </div>
  </div>

  <div class="card">
    <h2>일별 추이</h2>
    @if ($chartTruncated)
      <p class="chart-note">그래프는 최근 {{ count($chart) }}일까지만 표시돼요. 위 통계 숫자는 선택한 전체 기간({{ $stDate }} ~ {{ $edDate }}) 기준이에요.</p>
    @endif
    <div class="chart-block">
      <div class="chart-block-label">일별 방문자(고유)</div>
      <div class="bar-chart">
        @foreach ($chart as $day)
          <div class="bar-col" title="{{ $day['label'] }}: {{ $day['visitors'] }}명">
            <div class="bar-value">{{ $day['visitors'] }}</div>
            <div class="bar-track">
              <div class="bar" style="height: {{ max(2, $day['visitors_pct']) }}%; background: var(--indigo);"></div>
            </div>
            <div class="bar-label">{{ $day['label'] }}</div>
          </div>
        @endforeach
      </div>
    </div>
    <div class="chart-block">
      <div class="chart-block-label">일별 매출 (만원)</div>
      <div class="bar-chart">
        @foreach ($chart as $day)
          <div class="bar-col" title="{{ $day['label'] }}: {{ number_format($day['revenue']) }}원">
            <div class="bar-value">{{ $day['revenue_manwon_label'] }}</div>
            <div class="bar-track">
              <div class="bar" style="height: {{ max(2, $day['revenue_pct']) }}%; background: var(--seal);"></div>
            </div>
            <div class="bar-label">{{ $day['label'] }}</div>
          </div>
        @endforeach
      </div>
    </div>
  </div>

  <div class="card">
    <h2>선택 기간 결제 내역 (최근 10건)</h2>
    @if ($recentPayments->isEmpty())
      <div class="empty-state">선택한 기간에는 결제 기록이 없어요.</div>
    @else
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr><th>일시</th><th>사용자</th><th>플랜</th><th>코인</th><th>금액</th></tr>
          </thead>
          <tbody>
            @foreach ($recentPayments as $payment)
              <tr>
                <td>{{ $payment->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $payment->user->name ?? '탈퇴한 사용자' }}</td>
                <td>{{ $payment->plan }}</td>
                <td>{{ $payment->credits }}개</td>
                <td>{{ number_format($payment->amount) }}원</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card">
    <h2>선택 기간 리포트 판매 내역 (최근 10건)</h2>
    @if ($recentReports->isEmpty())
      <div class="empty-state">선택한 기간에는 리포트 판매 기록이 없어요.</div>
    @else
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr><th>일시</th><th>사용자</th><th>종류</th><th>제목</th><th>금액</th></tr>
          </thead>
          <tbody>
            @foreach ($recentReports as $report)
              <tr>
                <td>{{ $report->created_at->format('Y-m-d H:i') }}</td>
                <td>{{ $report->user->name ?? '탈퇴한 사용자' }}</td>
                <td>{{ ['single' => '심층 연애 리포트', 'compat' => '프리미엄 궁합 리포트', 'love_fortune' => '연애운분석', 'compatibility' => '궁합분석'][$report->type] ?? $report->type }}</td>
                <td>{{ $report->title ?? '-' }}</td>
                <td>{{ number_format($report->amount) }}원</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <footer>
    방문자 수는 브라우저 쿠키 기반의 대략적인 집계예요(광고 유입 경로·봇 필터링 등은 포함하지 않음). 정확한 마케팅 분석이 필요하면 GA4 같은 별도 분석 도구를 붙이는 걸 추천해요. 위 매출/결제 통계는 코인 결제와 프리미엄 리포트 결제를 합산한 값이에요.
  </footer>
</div>

</body>
</html>
