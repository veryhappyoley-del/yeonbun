<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $report->title ?: ($type['label'] ?? '리포트') }} — 결</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="wrap wrap-narrow">

  <div class="topbar">
    <a class="chip-link" href="{{ route('reports.index') }}">&larr; 내 리포트함으로</a>
  </div>

  <div class="card" style="margin-top:18px;">
    <h2>{{ $report->title ?: ($type['label'] ?? '리포트') }}</h2>

    <div class="report-meta">
      <span>{{ $type['label'] ?? $report->type }}</span>
      <span>{{ number_format($report->amount) }}원 결제완료</span>
      <span>{{ $report->updated_at->format('Y.m.d H:i') }}</span>
    </div>

    @if ($report->content)
      <div class="report-body">
        {!! $report->content !!}
      </div>
    @else
      <div id="report-pending-note" class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
        리포트를 만들고 있어요. 10~30초 정도 걸릴 수 있어요 — 결제는 이미 정상적으로 완료됐으니 안심하고 잠시만 기다려 주세요.
      </div>
      <form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center;">
        @csrf
        <button type="submit" class="btn" id="regenerate-btn">리포트 다시 생성하기</button>
      </form>
      <script>
        (function () {
          var key = 'gyeol_report_autogen_{{ $report->id }}';
          var alreadyTried = false;
          try { alreadyTried = !!sessionStorage.getItem(key); } catch (e) {}

          if (alreadyTried) {
            // 자동 재시도를 이미 한 번 해봤는데도 비어있는 상태 — 무한 반복을 막고 수동 버튼만 남겨둠.
            document.getElementById('report-pending-note').textContent =
              '리포트를 생성하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.';
            return;
          }

          try { sessionStorage.setItem(key, '1'); } catch (e) {}
          document.getElementById('regenerate-btn').disabled = true;
          document.getElementById('regenerate-btn').textContent = '생성하는 중…';
          document.getElementById('regenerate-form').submit();
        })();
      </script>
    @endif
  </div>

  <footer>
    이 리포트는 AI가 사주 계산 결과를 바탕으로 생성한 해석 콘텐츠로, 통계적·성향적 참고용입니다. 연애의 실제 결과를 보장하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

</body>
</html>
