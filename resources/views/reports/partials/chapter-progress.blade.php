{{--
  챕터형(schema_version=2) 리포트의 "생성 중" 화면. 레거시 pending.blade.php는 예상
  소요 시간 기준의 가짜 진행률(실제 서버가 몇 % 완료됐는지 알 방법이 없어서)을 썼지만,
  이 화면은 /reports/{report}/status가 내려주는 실제 챕터 상태({total, completed, failed})
  를 그대로 퍼센트로 씁니다 — "20개 중 7개 완료"가 곧 35%이므로, 진짜 진행률을 보여줄
  수 있습니다.

  기대 변수: $report (App\Models\Report).
--}}
<div id="chapter-progress-note" class="placeholder-note" style="border-color: var(--seal); margin-top:0;">
  리포트를 만들고 있어요 — 결제는 이미 정상적으로 완료됐으니 이 화면을 열어둔 채로 잠시만 기다려 주세요. 챕터가 하나씩 완료되는 대로 진행률이 올라가요.
</div>

<div class="rpt-gauge" id="chapter-progress-gauge">
  <svg class="rpt-gauge-svg" viewBox="0 0 120 120">
    <circle class="rpt-gauge-track" cx="60" cy="60" r="52" />
    <circle class="rpt-gauge-fill" id="chapter-progress-fill" cx="60" cy="60" r="52" />
  </svg>
  <div class="rpt-gauge-percent" id="chapter-progress-percent">0%</div>
</div>
<div class="chapter-progress-count" id="chapter-progress-count">챕터 준비 중…</div>

<script>
  (function () {
    var statusUrl = @json(route('reports.status', $report));
    var fillEl = document.getElementById('chapter-progress-fill');
    var percentEl = document.getElementById('chapter-progress-percent');
    var countEl = document.getElementById('chapter-progress-count');
    var noteEl = document.getElementById('chapter-progress-note');

    // 원형 게이지의 둘레(SVG circle r=52 기준, 2πr). 이 화면은 레거시 pending.blade.php와
    // 달리 시간 기반 타이머 없이, 매 폴링 응답이 곧 실제 진행률이라 stroke-dashoffset을
    // 그 자리에서 바로 계산해서 적용합니다.
    var circumference = 2 * Math.PI * 52;
    fillEl.style.strokeDasharray = circumference.toFixed(2);

    function render(percent) {
      var shown = Math.min(100, Math.round(percent));
      fillEl.style.strokeDashoffset = (circumference * (1 - shown / 100)).toFixed(2);
      percentEl.textContent = shown + '%';
    }

    var pollAttempts = 0;
    // 챕터 20개 × 동시성 4 기준으로 배치 5번, 배치당 최대 90초라 최대 ~7.5분 정도
    // 걸릴 수 있음을 여유 있게 잡음(3초 간격 폴링 × 150회 ≈ 7.5분).
    var maxPollAttempts = 150;

    function poll() {
      pollAttempts += 1;

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          var total = data.total || 0;
          var completed = data.completed || 0;
          var failed = data.failed || 0;

          render(total > 0 ? ((completed + failed) / total) * 100 : 0);
          countEl.textContent = completed + ' / ' + total + ' 챕터 완료' + (failed > 0 ? ' · ' + failed + '개 재시도 필요' : '');

          if (data.ready) {
            noteEl.textContent = '리포트가 준비됐어요! 불러오는 중…';
            setTimeout(function () { window.location.reload(); }, 400);
            return;
          }

          if (pollAttempts < maxPollAttempts) {
            setTimeout(poll, 3000);
          } else {
            noteEl.textContent = '생성이 예상보다 오래 걸리고 있어요. 결제는 정상적으로 완료됐으니 안심하시고, 잠시 후 페이지를 새로고침해 주세요.';
          }
        })
        .catch(function () {
          if (pollAttempts < maxPollAttempts) {
            setTimeout(poll, 3000);
          }
        });
    }

    poll();
  })();
</script>
