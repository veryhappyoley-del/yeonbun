{{-- content(single은 JSON, compat은 HTML)가 아직 없을 때 공통으로 쓰는 "생성 중" / 재시도 UI.

     리포트 생성은 큐(백그라운드 워커)가 처리합니다 — 이 화면은 새로고침 없이
     /reports/{report}/status 를 몇 초 간격으로 폴링하다가 준비되면 스스로 새로고침합니다.

     텍스트로 "30~90초 정도 걸려요"라고만 안내하는 대신, 예상 소요 시간을 기준으로
     조금씩 차오르는 게이지바를 보여줍니다. 실제 진행률을 서버가 알려줄 방법은 없어서
     (AI가 한 번에 응답을 만들기 때문에 "몇 % 완료" 같은 중간 상태가 없음) 이 게이지는
     "예상 소요 시간 기준의 가짜 진행률"입니다 — 그래서 실제 완료 전에 100%를 찍어버리면
     "다 됐는데 왜 안 넘어가지?"처럼 보일 수 있어, 96%에서 멈춰 기다리다가 실제로 완료
     신호(status API의 ready:true)를 받는 순간에만 100%를 채우고 새로고침합니다. --}}
<div id="report-pending-note" class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
  리포트를 만들고 있어요 — 결제는 이미 정상적으로 완료됐으니 이 화면을 열어둔 채로 잠시만 기다려 주세요. 완료되면 자동으로 새로고침돼요.
</div>
<div class="rpt-progress" id="report-progress">
  <div class="rpt-progress-track">
    <div class="rpt-progress-fill" id="report-progress-fill" style="width:0%"></div>
  </div>
  <div class="rpt-progress-percent" id="report-progress-percent">0%</div>
</div>
<form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center; display:none;">
  @csrf
  <button type="submit" class="btn" id="regenerate-btn">리포트 다시 생성하기</button>
</form>
<script>
  (function () {
    var statusUrl = @json(route('reports.status', $report));
    var isSingle = @json($report->type !== 'compat');

    // 게이지바가 "몇 초 만에 96%까지 찰지" 기준이 되는 예상 소요 시간(초).
    // single(심층 연애 리포트)은 최대 120초, compat(궁합 리포트)은 최대 30초 정도를 기준으로 잡음.
    var estimatedSeconds = isSingle ? 120 : 30;
    var capPercent = 96; // 실제 완료 전에는 여기서 멈춰서 대기(100%는 완료 신호를 받은 순간에만 채움)
    var tickMs = 300;
    var incrementPerTick = capPercent / (estimatedSeconds * 1000 / tickMs);

    var noteEl = document.getElementById('report-pending-note');
    var formEl = document.getElementById('regenerate-form');
    var progressWrap = document.getElementById('report-progress');
    var fillEl = document.getElementById('report-progress-fill');
    var percentEl = document.getElementById('report-progress-percent');

    var percent = 0;
    var done = false;
    var pollIntervalMs = 3000;
    var maxPollAttempts = 40; // 약 2분
    var pollAttempts = 0;
    var barTimer = null;
    var pollTimer = null;

    function renderPercent(p) {
      var shown = Math.min(100, Math.round(p));
      fillEl.style.width = shown + '%';
      percentEl.textContent = shown + '%';
    }

    function tickBar() {
      if (done || percent >= capPercent) return;
      percent = Math.min(capPercent, percent + incrementPerTick);
      renderPercent(percent);
    }

    function finishBar() {
      done = true;
      if (barTimer) clearInterval(barTimer);
      renderPercent(100);
    }

    function showManualRetry(message) {
      done = true;
      if (barTimer) clearInterval(barTimer);
      if (pollTimer) clearInterval(pollTimer);
      noteEl.textContent = message;
      progressWrap.style.display = 'none';
      formEl.style.display = 'block';
    }

    function poll() {
      pollAttempts += 1;

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          if (data && data.ready) {
            if (pollTimer) clearInterval(pollTimer);
            finishBar();
            noteEl.textContent = '리포트가 준비됐어요! 불러오는 중…';
            setTimeout(function () { window.location.reload(); }, 400);
            return;
          }
          if (pollAttempts >= maxPollAttempts) {
            showManualRetry('리포트 생성이 예상보다 오래 걸리고 있어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.');
          }
        })
        .catch(function () {
          if (pollAttempts >= maxPollAttempts) {
            showManualRetry('상태를 확인하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.');
          }
        });
    }

    barTimer = setInterval(tickBar, tickMs);
    pollTimer = setInterval(poll, pollIntervalMs);
    poll();
  })();
</script>
