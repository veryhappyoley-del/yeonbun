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
    <div class="stat-card">
      <div class="stat-label">객단가 (ARPU)</div>
      <div class="stat-value">{{ number_format($arpu) }}<span>원</span></div>
      <div class="stat-sub">결제 고객 1인당 평균 결제액</div>
    </div>
  </div>

  {{-- (2026-08-26 추가) 상품별 매출 / 결제 퍼널 / 가입 경로 / 무료 미리보기 현황.
       사용자가 "관리자 대시보드에 더 다양한 데이터를 보고 싶다"고 요청해서 추가했다. --}}
  <div class="card">
    <h2>상품별 매출</h2>
    @if ($revenueByProduct->isEmpty())
      <div class="empty-state">선택한 기간에는 매출이 없어요.</div>
    @else
      <div style="overflow-x:auto;">
        <table class="admin-table">
          <thead>
            <tr><th>상품</th><th>건수</th><th>매출</th><th>비중</th></tr>
          </thead>
          <tbody>
            @foreach ($revenueByProduct as $row)
              <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ number_format($row['count']) }}건</td>
                <td>{{ number_format($row['amount']) }}원</td>
                <td>{{ $totalRevenue > 0 ? round($row['amount'] / $totalRevenue * 100, 1) : 0 }}%</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>

  <div class="card">
    <h2>결제 퍼널 이탈 현황</h2>
    <p class="chart-note">결제창까지 진행됐지만(행 생성) 승인이 안 끝난 건(대기중)과 실패한 건을 완료 건과 함께 보여줘요.</p>
    <div style="overflow-x:auto;">
      <table class="admin-table">
        <thead>
          <tr><th>구분</th><th>시도</th><th>완료</th><th>대기중</th><th>실패</th><th>완료율</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>코인 충전</td>
            <td>{{ number_format($paymentFunnel['started']) }}</td>
            <td>{{ number_format($paymentFunnel['paid']) }}</td>
            <td>{{ number_format($paymentFunnel['pending']) }}</td>
            <td>{{ number_format($paymentFunnel['failed']) }}</td>
            <td>{{ $paymentFunnel['completion_rate'] }}%</td>
          </tr>
          <tr>
            <td>리포트 결제</td>
            <td>{{ number_format($reportFunnel['started']) }}</td>
            <td>{{ number_format($reportFunnel['paid']) }}</td>
            <td>{{ number_format($reportFunnel['pending']) }}</td>
            <td>{{ number_format($reportFunnel['failed']) }}</td>
            <td>{{ $reportFunnel['completion_rate'] }}%</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <h2>가입 경로 비율</h2>
    @if ($signupsByProvider->sum('count') === 0)
      <div class="empty-state">선택한 기간에는 신규 가입이 없어요.</div>
    @else
      <div class="badge-row">
        @foreach ($signupsByProvider as $row)
          <span class="badge indigo">{{ $row['label'] }} {{ $row['pct'] }}% ({{ number_format($row['count']) }}명)</span>
        @endforeach
      </div>
    @endif
  </div>

  <div class="card">
    <h2>무료 미리보기(챕터 프리뷰) 생성 현황</h2>
    <p class="chart-note">결제 전 무료로 보여주는 챕터 1개가 실제로 얼마나 생성되는지, AI 생성 실패율은 얼마나 되는지예요. 로그인 없이 익명으로 동작해서 개별 이용자로는 연결되지 않고 전체 건수만 집계돼요.</p>
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-label">생성 시도</div>
        <div class="stat-value">{{ number_format($previewTotal) }}<span>건</span></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">성공</div>
        <div class="stat-value">{{ number_format($previewReady) }}<span>건</span></div>
        <div class="stat-sub">{{ $previewTotal > 0 ? round($previewReady / $previewTotal * 100, 1) : 0 }}%</div>
      </div>
      <div class="stat-card">
        <div class="stat-label">실패</div>
        <div class="stat-value">{{ number_format($previewFailed) }}<span>건</span></div>
        <div class="stat-sub">{{ $previewTotal > 0 ? round($previewFailed / $previewTotal * 100, 1) : 0 }}%</div>
      </div>
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
                <td>{{ $planLabels[$payment->plan]['label'] ?? $payment->plan }}</td>
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
                <td>{{ $reportTypeLabels[$report->type] ?? $report->type }}</td>
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
