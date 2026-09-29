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

{{--
  (2026-08-24 추가) 진행 단계 체크리스트 — "그냥 원형 게이지 하나만 돌아가니 뭘 하고 있는지
  모르겠다"는 지적에 대응해, 실제 챕터 완료율(위 원형 게이지와 같은 percent 값)에 맞춰
  5단계 체크리스트를 함께 보여준다. 가짜 타이머가 아니라 real percent 임계값으로 단계를
  판단하므로(아래 STEPS의 threshold), "전문적으로 집중 탐구하는 느낌"을 실제 진행 상황과
  어긋나지 않게 준다. 두 리포트 타입(연애운분석/궁합분석) 모두에 공통으로 쓰이는 화면이라
  문구도 특정 타입에 치우치지 않게 일반적으로 썼다.
--}}
<div class="progress-steps" id="chapter-progress-steps"></div>

{{-- (2026-09-28 추가) 막다른 길 방지.
     지금까지 이 화면은 폴링이 끝까지(약 7.5분) 실패하면 "잠시 후 새로고침해 주세요"라는
     문구만 바꾸고 끝이었다. 재시도 버튼도, 문의 경로도 없어서 결제한 사용자가 갈 곳이
     없었다(출시전 점검리스트 3항). 아래 블록을 그때 보여준다. --}}
<div id="chapter-progress-stuck" style="display:none;">
  <form method="POST" action="{{ route('reports.regenerate', $report) }}" class="regenerate-form" id="chapter-regenerate-form" style="display:block;">
    @csrf
    <button type="submit" class="btn btn-center" id="chapter-regenerate-btn">리포트 다시 생성하기</button>
  </form>
  @include('reports.partials.support-notice', ['report' => $report])
</div>

{{-- 세션이 끊겨 상태를 확인할 수 없을 때. --}}
<div class="feedback feedback--error report-pending-alert" id="chapter-session-expired" role="alert" style="display:none;">
  @include('partials.icon', ['name' => 'alert'])
  <div class="feedback-body">
    로그인이 풀려서 상태를 확인할 수 없어요. 결제는 정상적으로 완료됐으니 다시 로그인하면 이어서 보실 수 있어요.
    <a class="chip-link" href="{{ route('my.index') }}">다시 로그인하기</a>
  </div>
</div>

<script>
  (function () {
    var statusUrl = @json(route('reports.status', $report));
    var fillEl = document.getElementById('chapter-progress-fill');
    var percentEl = document.getElementById('chapter-progress-percent');
    var countEl = document.getElementById('chapter-progress-count');
    var noteEl = document.getElementById('chapter-progress-note');
    var stepsEl = document.getElementById('chapter-progress-steps');
    var stuckEl = document.getElementById('chapter-progress-stuck');
    var expiredEl = document.getElementById('chapter-session-expired');
    var gaugeEl = document.getElementById('chapter-progress-gauge');

    function showStuck(message) {
      noteEl.textContent = message;
      stuckEl.style.display = 'block';
    }

    function showSessionExpired() {
      gaugeEl.style.display = 'none';
      stepsEl.style.display = 'none';
      noteEl.style.display = 'none';
      countEl.style.display = 'none';
      expiredEl.style.display = 'block';
    }

    // 원형 게이지의 둘레(SVG circle r=52 기준, 2πr). 이 화면은 레거시 pending.blade.php와
    // 달리 시간 기반 타이머 없이, 매 폴링 응답이 곧 실제 진행률이라 stroke-dashoffset을
    // 그 자리에서 바로 계산해서 적용합니다.
    var circumference = 2 * Math.PI * 52;
    fillEl.style.strokeDasharray = circumference.toFixed(2);

    // threshold = 이 단계가 "진행 중"으로 표시되기 시작하는 percent. 다음 단계의
    // threshold에 도달하면 그 앞 단계는 완료(✓) 처리된다(마지막 단계는 data.ready일 때만
    // 완료 처리 — percent가 100이어도 서버가 아직 최종 상태를 반영 중일 수 있어서).
    var STEPS = [
      { title: '명식을 펼칩니다', desc: '입력하신 생년월일시를 사주 원국으로 정리하고 있어요.', threshold: 0 },
      { title: '오행·십신 데이터를 대조합니다', desc: '오행 균형과 십신 분포, 신강신약을 하나씩 짚어보고 있어요.', threshold: 15 },
      { title: '챕터별로 나눠 심층 해석을 씁니다', desc: '주제 하나하나를 깊이 파고들어 촘촘하게 풀어내고 있어요.', threshold: 35 },
      { title: '표와 그래프로 구조화합니다', desc: '점수·흐름·비교 데이터를 시각 자료로 정리하고 있어요.', threshold: 70 },
      { title: '최종 리포트를 완성합니다', desc: '챕터를 모두 모아 최종 리포트로 마무리하고 있어요.', threshold: 92 }
    ];

    function renderSteps(percent, ready) {
      stepsEl.innerHTML = '';
      STEPS.forEach(function (step, i) {
        var isLast = i === STEPS.length - 1;
        var nextThreshold = isLast ? 100 : STEPS[i + 1].threshold;
        var state = 'pending';
        if (ready || (isLast ? percent >= 100 : percent >= nextThreshold)) state = 'done';
        else if (percent >= step.threshold) state = 'active';

        var row = document.createElement('div');
        row.className = 'progress-step ' + state;

        var icon = document.createElement('div');
        icon.className = 'progress-step-icon';
        icon.textContent = state === 'done' ? '✓' : (state === 'active' ? '●' : '○');
        row.appendChild(icon);

        var body = document.createElement('div');
        body.className = 'progress-step-body';
        var title = document.createElement('div');
        title.className = 'progress-step-title';
        title.textContent = step.title;
        body.appendChild(title);
        if (state === 'active') {
          var desc = document.createElement('div');
          desc.className = 'progress-step-desc';
          desc.textContent = step.desc;
          body.appendChild(desc);
        }
        row.appendChild(body);

        stepsEl.appendChild(row);
      });
    }

    function render(percent, ready) {
      var shown = Math.min(100, Math.round(percent));
      fillEl.style.strokeDashoffset = (circumference * (1 - shown / 100)).toFixed(2);
      percentEl.textContent = shown + '%';
      renderSteps(shown, ready);
    }

    render(0, false);

    // 재시도 버튼도 연타로 같은 작업이 두 번 큐에 올라가지 않게 잠근다(pending.blade.php와 동일).
    document.getElementById('chapter-regenerate-form').addEventListener('submit', function () {
      var btn = document.getElementById('chapter-regenerate-btn');
      if (!btn) return;
      btn.disabled = true;
      btn.classList.add('is-loading');
      btn.setAttribute('aria-busy', 'true');
    });

    var pollAttempts = 0;
    // 챕터 20개 × 동시성 4 기준으로 배치 5번, 배치당 최대 90초라 최대 ~7.5분 정도
    // 걸릴 수 있음을 여유 있게 잡음(3초 간격 폴링 × 150회 ≈ 7.5분).
    var maxPollAttempts = 150;

    function poll() {
      pollAttempts += 1;

      fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          // (2026-09-28) 세션이 끊기면 로그인 화면으로 리다이렉트되거나 401/419가 온다.
          // 예전에는 둘 다 catch가 조용히 삼켜서 7.5분 동안 빈 진행률만 돌았다.
          if (res.status === 401 || res.status === 419 || res.redirected) { showSessionExpired(); return null; }
          if (!res.ok) throw new Error('status check failed');
          return res.json();
        })
        .then(function (data) {
          if (data === null) return;
          var total = data.total || 0;
          var completed = data.completed || 0;
          var failed = data.failed || 0;

          render(total > 0 ? ((completed + failed) / total) * 100 : 0, !!data.ready);
          countEl.textContent = completed + ' / ' + total + ' 챕터 완료' + (failed > 0 ? ' · ' + failed + '개 재시도 필요' : '');

          if (data.ready) {
            noteEl.textContent = '리포트가 준비됐어요! 불러오는 중…';
            setTimeout(function () { window.location.reload(); }, 400);
            return;
          }

          if (pollAttempts < maxPollAttempts) {
            setTimeout(poll, 3000);
          } else {
            showStuck('생성이 예상보다 오래 걸리고 있어요. 아래 버튼으로 다시 시도해 보시고, 그래도 안 되면 문의해 주세요.');
          }
        })
        .catch(function () {
          if (pollAttempts < maxPollAttempts) {
            setTimeout(poll, 3000);
          } else {
            showStuck('상태를 확인하는 중 문제가 있었어요. 결제는 정상적으로 완료됐으니 아래 버튼으로 다시 시도해 주세요.');
          }
        });
    }

    poll();
  })();
</script>
