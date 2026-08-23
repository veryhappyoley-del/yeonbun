{{-- content(single은 JSON, compat은 HTML)가 아직 없을 때 공통으로 쓰는 "생성 중" / 재시도 UI.

     리포트 생성은 이제 큐(백그라운드 워커)가 처리합니다 — 이 화면은 새로고침 없이
     /reports/{report}/status 를 몇 초 간격으로 폴링하다가 준비되면 스스로 새로고침합니다.
     오래 걸려도(네트워크가 느리거나 워커가 밀려도) 폴링이 계속 재시도하므로 자동으로
     한 번만 시도하고 포기하던 예전 방식과 달리 페이지를 열어두기만 하면 됩니다.
     그래도 너무 오래 걸리면(예: 워커가 안 떠 있는 경우) 일정 시간 뒤 수동 "다시 생성하기"
     버튼을 보여줍니다. --}}
<div id="report-pending-note" class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
  리포트를 만들고 있어요. {{ $report->type === 'compat' ? '10~30초' : '30~90초' }} 정도 걸릴 수 있어요 — 결제는 이미 정상적으로 완료됐으니 이 화면을 열어둔 채로 잠시만 기다려 주세요. 완료되면 자동으로 새로고침돼요.
</div>
<form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" style="margin-top:14px; text-align:center; display:none;">
  @csrf
  <button type="submit" class="btn" id="regenerate-btn">리포트 다시 생성하기</button>
</form>
<script>
  (function () {
    var statusUrl = @json(route('reports.status', $report));
    var noteEl = document.getElementById('report-pending-note');
    var formEl = document.getElementById('regenerate-form');
    var intervalMs = 3000;
    var maxAttempts = 40; // 약 2분
    var attempts = 0;
    var timer = null;

    function showManualRetry(message) {
      if (timer) clearInterval(timer);
      noteEl.textContent = message;
      formEl.style.display = 'block';
    }

    function poll() {
      attempts += 1;

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          if (data && data.ready) {
            if (timer) clearInterval(timer);
            noteEl.textContent = '리포트가 준비됐어요! 불러오는 중…';
            window.location.reload();
            return;
          }
          if (attempts >= maxAttempts) {
            showManualRetry('리포트 생성이 예상보다 오래 걸리고 있어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.');
          }
        })
        .catch(function () {
          if (attempts >= maxAttempts) {
            showManualRetry('상태를 확인하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 안심하시고, 아래 버튼으로 다시 시도해 주세요.');
          }
        });
    }

    timer = setInterval(poll, intervalMs);
    poll();
  })();
</script>
