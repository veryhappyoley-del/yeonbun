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
  {{-- (2026-09-28) 이 파셜은 리포트 표지(h1)와 같은 문서에 렌더되므로 h2로 둔다. --}}
  <h2>결제는 이미 완료됐어요<br>잠시만 기다려 주세요</h2>
  <p id="report-pending-note" role="status" aria-live="polite">이 화면을 열어둔 채로 기다리시면, 완료되는 즉시 자동으로 열려요.</p>
  <div class="loading-bar" aria-hidden="true"><i></i></div>
  <small>앱을 닫지 않아도 자동으로 이동해요</small>
</div>
<div class="feedback feedback--error report-pending-alert" id="report-session-expired" role="alert" style="display:none;">
  @include('partials.icon', ['name' => 'alert'])
  <div class="feedback-body">
    로그인이 풀려서 상태를 확인할 수 없어요. 결제는 정상적으로 완료됐으니 다시 로그인하면 이어서 보실 수 있어요.
    <a class="chip-link" href="{{ route('my.index') }}">다시 로그인하기</a>
  </div>
</div>
<form id="regenerate-form" method="POST" action="{{ route('reports.regenerate', $report) }}" class="regenerate-form">
  @csrf
  <button type="submit" class="btn btn-center" id="regenerate-btn">리포트 다시 생성하기</button>
  {{-- (2026-09-28) 재시도해도 계속 실패할 때 갈 곳이 없던 문제 — 재시도 버튼과 같은
       자리에 문의·환불 경로를 항상 함께 둔다. 이 폼은 폴링이 끝까지 실패했을 때만
       보이므로, 여기까지 온 사용자에게는 이미 한 번 어긋난 상황이다. --}}
  @include('reports.partials.support-notice', ['report' => $report])
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

    // (2026-09-28 추가) 폴링이 로그인 화면으로 리다이렉트되는 경우(세션 만료)를 따로 안내한다.
    // 예전에는 리다이렉트된 HTML을 받아 res.json()에서 예외가 나고, 그 예외를 catch가 조용히
    // 삼켜서 최대 시도 횟수(single 기준 약 5.5분)까지 빈 로딩만 보였다. 결제 직후 화면이라
    // 그 5분이 그대로 불안으로 이어진다.
    function showSessionExpired() {
      if (pollTimer) clearInterval(pollTimer);
      progressWrap.style.display = 'none';
      formEl.style.display = 'none';
      var box = document.getElementById('report-session-expired');
      if (box) box.style.display = 'block';
    }

    function poll() {
      pollAttempts += 1;

      if (!slowShown && Date.now() - startedAt > slowAfterMs) {
        slowShown = true;
        noteEl.textContent = '조금 더 걸리고 있어요. 화면을 닫지 않으셔도 되고, 완료되면 자동으로 열려요.';
      }

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          // 세션이 끊기면 auth 미들웨어가 로그인 화면으로 보낸다(리다이렉트 200 + HTML),
          // 또는 XHR로 인식되면 401/419로 떨어진다. 둘 다 여기서 걸러 즉시 안내한다.
          if (res.status === 401 || res.status === 419) { showSessionExpired(); return null; }
          if (res.redirected) { showSessionExpired(); return null; }
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          if (data === null) return;   // 세션 만료 안내로 이미 전환됨
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

    // (2026-09-28 추가) "다시 생성하기"는 폴링이 실패한 뒤에 나타나는 버튼이라 연타하기
    // 쉬운 자리다. 제출 즉시 잠가서 같은 작업이 두 번 큐에 올라가지 않게 한다.
    formEl.addEventListener('submit', function () {
      var btn = document.getElementById('regenerate-btn');
      if (!btn) return;
      btn.disabled = true;
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
    });
  })();
</script>
