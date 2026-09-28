{{-- content(single은 JSON, compat은 HTML)가 아직 없을 때 공통으로 쓰는 "생성 중" / 재시도 UI.

     리포트 생성은 큐(백그라운드 워커)가 처리합니다 — 이 화면은 새로고침 없이
     /reports/{report}/status 를 몇 초 간격으로 폴링하다가 준비되면 스스로 새로고침합니다.

     (2026-09-28 개편) 예전에는 "예상 소요 시간 기준의 가짜 진행률"을 퍼센트로 보여줬습니다.
     서버가 실제 진행률을 알려줄 수 없는 화면(레거시 single/compat은 AI가 한 번에 응답을
     만들어서 중간 상태가 없음)이라, 숫자를 지어내는 대신 UI 전달본의 비결정형 로딩
     (loading.svg + 무한 진행 바)으로 바꿨습니다. 폴링/자동 새로고침/수동 재시도 로직과
     DOM id는 그대로라 동작은 동일합니다. --}}
<div class="center-state" id="report-progress">
  <img src="{{ asset('img/handoff/loading.svg') }}" alt="" aria-hidden="true">
  <span class="eyebrow">리포트를 만들고 있어요</span>
  <h1>결제는 이미 완료됐어요<br>잠시만 기다려 주세요</h1>
  <p id="report-pending-note" role="status" aria-live="polite">이 화면을 열어둔 채로 기다리시면, 완료되는 즉시 자동으로 열려요.</p>
  <div class="loading-bar" aria-hidden="true"><i></i></div>
  <small>앱을 닫지 않아도 자동으로 이동해요</small>
</div>
<form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center; display:none;">
  @csrf
  <button type="submit" class="btn btn-center" id="regenerate-btn">리포트 다시 생성하기</button>
</form>
<script>
  (function () {
    var statusUrl = @json(route('reports.status', $report));
    var isSingle = @json($report->type !== 'compat');

    var noteEl = document.getElementById('report-pending-note');
    var formEl = document.getElementById('regenerate-form');
    var progressWrap = document.getElementById('report-progress');

    var pollIntervalMs = 3000;
    // single은 GenerateReportJob의 타임아웃(300초)보다 여유 있게 잡아야, 정상적으로
    // 처리 중인데 너무 일찍 "다시 생성하기" 버튼을 보여주는 일이 없음. compat은 원래도 짧음.
    var maxPollAttempts = isSingle ? 110 : 30; // 약 5.5분 / 약 1.5분
    var pollAttempts = 0;
    var pollTimer = null;
    // "예상보다 오래 걸리는 중" 안내를 띄우는 시점(초). 진행률을 지어내지 않는 대신
    // 사용자가 얼마나 더 기다려야 하는지 상태 문구로 알려준다.
    var slowAfterMs = isSingle ? 90000 : 25000;
    var startedAt = Date.now();
    var slowShown = false;

    function showManualRetry(message) {
      if (pollTimer) clearInterval(pollTimer);
      noteEl.textContent = message;
      progressWrap.style.display = 'none';
      formEl.style.display = 'block';
    }

    function poll() {
      pollAttempts += 1;

      if (!slowShown && Date.now() - startedAt > slowAfterMs) {
        slowShown = true;
        noteEl.textContent = '조금 더 걸리고 있어요. 화면을 닫지 않으셔도 되고, 완료되면 자동으로 열려요.';
      }

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          if (data && data.ready) {
            if (pollTimer) clearInterval(pollTimer);
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

    pollTimer = setInterval(poll, pollIntervalMs);
    poll();
  })();
</script>
