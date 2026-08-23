/**
 * "심층 리포트 / 프리미엄 궁합 리포트(유료)" + "공유 카드(무료)" 버튼을
 * 사주풀이/궁합 결과 카드 아래에 붙여주는 스크립트.
 *
 * app.js가 결과를 렌더링한 직후 window.YeonbunReports.attachSingleCTA / attachCompatCTA를
 * 호출해서 이 스크립트에 결과 데이터를 넘겨줍니다. 결제는 billing.blade.php와 동일한
 * 토스페이먼츠 위젯 방식이고, 서버(ReportController)가 가격을 최종적으로 결정합니다.
 * 공유 카드는 순수 클라이언트 <canvas> 렌더링이라 서버 호출이 없어요(로그인 불필요).
 */
(function () {
  'use strict';

  // 화면 표시용 가격 안내일 뿐, 실제 결제 금액은 항상 서버(ReportController::TYPES)가 결정합니다.
  var TYPE_INFO = {
    single: { label: '심층 연애 리포트', priceLabel: '4,900원' },
    compat: { label: '프리미엄 궁합 리포트', priceLabel: '3,900원' }
  };

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    (children || []).forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function txt(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = text;
    return node;
  }

  /* ============================================================
   * 결제 흐름 (프리미엄 리포트)
   * ============================================================ */

  function startCheckout(type, input, title, statusBox) {
    if (!window.YeonbunAuth || !window.YeonbunAuth.loggedIn) {
      showLoginGate(statusBox);
      return;
    }

    if (!window.YeonbunBilling || !window.YeonbunBilling.tossConfigured) {
      statusBox.textContent = '결제 기능이 아직 설정되지 않았어요.';
      return;
    }

    statusBox.textContent = '결제창을 여는 중…';

    fetch(window.YeonbunBilling.reportsCheckoutUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: JSON.stringify({ type: type, input: input, title: title })
    })
      .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
      .then(function (r) {
        if (!r.ok) throw new Error(r.body.error || '리포트 결제를 시작하지 못했어요.');

        statusBox.textContent = '';
        var tossPayments = TossPayments(window.YeonbunBilling.tossClientKey);

        return tossPayments.requestPayment('카드', {
          amount: r.body.amount,
          orderId: r.body.order_id,
          orderName: r.body.order_name,
          customerName: r.body.customer_name,
          successUrl: window.YeonbunBilling.reportsSuccessUrl,
          failUrl: window.YeonbunBilling.reportsFailUrl
        });
      })
      .catch(function (error) {
        if (error && error.code === 'USER_CANCEL') { statusBox.textContent = ''; return; }
        statusBox.textContent = (error && error.message) || '리포트 결제 중 문제가 발생했어요.';
      });
  }

  function showLoginGate(statusBox) {
    statusBox.innerHTML = '';
    statusBox.appendChild(txt('div', 'hint', '로그인하면 프리미엄 리포트를 구매할 수 있어요.'));
    var row = el('div', { class: 'login-gate-inline' });
    row.appendChild(el('a', { class: 'social-btn kakao', href: window.YeonbunBilling.kakaoLoginUrl }, [document.createTextNode('카카오로 로그인')]));
    row.appendChild(el('a', { class: 'social-btn naver', href: window.YeonbunBilling.naverLoginUrl }, [document.createTextNode('네이버로 로그인')]));
    statusBox.appendChild(row);
  }

  /* ============================================================
   * input(JSON) 빌더 — 서버에는 사주 계산 로직이 전혀 없으므로
   * 여기서 이미 계산된 결과를 요약해서 넘깁니다.
   * ============================================================ */

  function pillarSummary(p) {
    if (!p) return null;
    return {
      stem: p.stem, stemHanja: p.stemHanja, stemElement: p.stemElement, stemYinYang: p.stemYinYang,
      branch: p.branch, branchHanja: p.branchHanja, branchElement: p.branchElement, branchYinYang: p.branchYinYang,
      label: p.label, hanja: p.hanja
    };
  }

  // 무료 "나의 연애 사주" 탭에서 이미 보여준 연애 캐릭터 유형(있다면)을 심층 리포트에도 넘겨서
  // AI가 "왜 이 유형으로 나왔는지"를 사주 데이터로 설명하도록 한다(같은 요약 반복 방지).
  function characterSummary(saju, love) {
    if (!window.YeonbunLoveCharacter) return null;
    var c = window.YeonbunLoveCharacter.getCharacter(love.dayEl, love.dayYY);
    if (!c) return null;
    return { typeName: c.typeName, oneLiner: c.oneLiner, signatureStat: c.signatureStat, trait: c.trait };
  }

  function buildSingleInput(state) {
    var saju = state.saju, love = state.love;
    return {
      name: state.name || null,
      pillars: {
        year: pillarSummary(saju.year),
        month: pillarSummary(saju.month),
        day: pillarSummary(saju.day),
        hour: pillarSummary(saju.hour)
      },
      dayElement: love.dayEl,
      dayYinYang: love.dayYY,
      wuxingCount: saju.wuxingCount,
      sinsals: love.sinsals,
      strongElements: love.strong,
      weakElements: love.weak,
      loveStyle: love.base.style,
      loveCharm: love.base.charm,
      loveCaution: love.base.caution,
      // 심층 사주 엔진(app.js analyzeDeepSaju) 결과 — 십신/지장간/합충형파해/신강신약/용신(간이).
      deep: saju.deep || null,
      // 무료 캐릭터 카드와 동일한 유형(있다면) — 프리미엄 리포트가 "왜 이 유형인지"를 설명하도록.
      characterType: characterSummary(saju, love)
    };
  }

  function buildCompatInput(state) {
    var a = state.sajuA, b = state.sajuB, c = state.compat;
    return {
      nameA: state.nameA || 'A',
      nameB: state.nameB || 'B',
      dayA: { stem: a.day.stem, branch: a.day.branch, element: a.day.stemElement },
      dayB: { stem: b.day.stem, branch: b.day.branch, element: b.day.stemElement },
      score: c.score,
      levelLabel: c.levelLabel,
      notes: c.notes,
      relation: c.rel
    };
  }

  /* ============================================================
   * CTA(구매 + 공유카드 버튼) 렌더링
   * ============================================================ */

  function buildCTA(typeKey, buildTitle, onShare) {
    var info = TYPE_INFO[typeKey];
    var wrap = el('div', { class: 'report-cta' });
    wrap.appendChild(txt('p', '', '더 깊은 해석이 궁금하다면?'));

    var row = el('div', { class: 'share-card-actions' });
    var statusBox = txt('div', 'hint', '');
    statusBox.style.width = '100%';
    statusBox.style.marginTop = '10px';
    statusBox.style.marginBottom = '0';

    var buyBtn = el('button', { type: 'button', class: 'btn' }, [document.createTextNode(info.label + ' 보기 · ' + info.priceLabel)]);
    buyBtn.addEventListener('click', function () {
      startCheckout(typeKey, buildTitle.input, buildTitle.title, statusBox);
    });
    row.appendChild(buyBtn);

    var shareBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('공유 카드 만들기')]);
    var previewBox = el('div', { class: 'share-preview' });
    shareBtn.addEventListener('click', function () {
      onShare(previewBox);
    });
    row.appendChild(shareBtn);

    wrap.appendChild(row);
    wrap.appendChild(statusBox);
    wrap.appendChild(previewBox);
    return wrap;
  }

  function attachSingleCTA(card, state) {
    var input = buildSingleInput(state);
    var title = (state.name ? state.name + '님의 ' : '') + '심층 연애 리포트';
    card.appendChild(buildCTA('single', { input: input, title: title }, function (previewBox) {
      renderShareCard(previewBox, 'single', state);
    }));
  }

  function attachCompatCTA(card, state) {
    var input = buildCompatInput(state);
    var title = (state.nameA || 'A') + ' × ' + (state.nameB || 'B') + ' 프리미엄 궁합 리포트';
    card.appendChild(buildCTA('compat', { input: input, title: title }, function (previewBox) {
      renderShareCard(previewBox, 'compat', state);
    }));
  }

  function truncate(str, n) {
    if (!str) return '';
    str = String(str);
    return str.length > n ? str.slice(0, n - 1) + '…' : str;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ============================================================
   * 공유 카드 — HTML/CSS로 디자인한 카드를 html2canvas로 그대로 이미지 캡처.
   * 서버 호출이 없어서 로그인하지 않아도 만들 수 있어요(바이럴 유입용).
   * ============================================================ */

  // 일간 오행 × 음양 조합(10가지)에 붙이는 트렌디한 "연애 유형" 닉네임.
  // 기존 해석 로직(app.js의 ELEMENT_LOVE)은 건드리지 않고, 공유 카드에서만 쓰는 재미 요소예요.
  var TYPE_NAME = {
    '목_양': { name: '직진 대시형', emoji: '🌱' },
    '목_음': { name: '은근 성장형', emoji: '🌿' },
    '화_양': { name: '텐션 메이커형', emoji: '🔥' },
    '화_음': { name: '잔잔한 불꽃형', emoji: '🕯️' },
    '토_양': { name: '든든한 베이스형', emoji: '🏡' },
    '토_음': { name: '묵묵한 진심형', emoji: '🌾' },
    '금_양': { name: '확신의 아이코닉형', emoji: '✨' },
    '금_음': { name: '츤데레 원칙형', emoji: '❄️' },
    '수_양': { name: '센스만렙 눈치형', emoji: '💧' },
    '수_음': { name: '잔잔한 감성형', emoji: '🌊' }
  };

  var CARD_CSS = '' +
    '.gyeol-card { width: 1080px; height: 1080px; position: relative; overflow: hidden; ' +
    '  box-sizing: border-box; font-family: "Gowun Dodum", sans-serif; color: #201d1a; ' +
    '  background: linear-gradient(135deg, #6B4460 0%, #8B5E83 48%, #E8735B 100%); }' +
    '.gyeol-card * { box-sizing: border-box; }' +
    '.gyeol-blob { position: absolute; border-radius: 50%; }' +
    '.gyeol-sparkle { position: absolute; font-size: 40px; opacity: 0.85; }' +
    '.gyeol-kicker { position: absolute; top: 56px; left: 56px; padding: 12px 26px; ' +
    '  background: rgba(255,255,255,0.22); border-radius: 999px; color: #fff; ' +
    '  font-size: 26px; font-weight: 700; letter-spacing: 0.02em; }' +
    '.gyeol-panel { position: absolute; left: 64px; right: 64px; top: 220px; bottom: 176px; ' +
    '  background: rgba(253,247,240,0.96); border-radius: 44px; ' +
    '  display: flex; flex-direction: column; align-items: center; justify-content: center; ' +
    '  padding: 60px 56px; text-align: center; }' +
    '.gyeol-emoji { font-size: 128px; line-height: 1; margin-bottom: 12px; }' +
    '.gyeol-type { font-family: "Black Han Sans", sans-serif; font-size: 68px; color: #6B4460; ' +
    '  line-height: 1.25; margin-bottom: 18px; }' +
    '.gyeol-names { font-family: "Black Han Sans", sans-serif; font-size: 54px; color: #6B4460; ' +
    '  line-height: 1.3; margin-bottom: 20px; }' +
    '.gyeol-tagline { font-size: 32px; color: #57524a; line-height: 1.6; margin-bottom: 22px; }' +
    '.gyeol-chip { display: inline-block; padding: 12px 24px; margin: 0 6px 10px; ' +
    '  background: rgba(139,94,131,0.12); border: 2px solid rgba(139,94,131,0.35); ' +
    '  border-radius: 999px; font-size: 26px; color: #6B4460; font-weight: 700; }' +
    '.gyeol-score-row { display: flex; align-items: flex-end; justify-content: center; gap: 10px; margin-bottom: 14px; }' +
    '.gyeol-score { font-family: "Black Han Sans", sans-serif; font-size: 168px; color: #E8735B; line-height: 0.9; }' +
    '.gyeol-score-unit { font-size: 40px; color: #57524a; font-weight: 700; padding-bottom: 22px; }' +
    '.gyeol-gauge-track { width: 100%; max-width: 620px; height: 28px; border-radius: 999px; ' +
    '  background: rgba(139,94,131,0.16); margin: 6px 0 22px; overflow: hidden; }' +
    '.gyeol-gauge-fill { height: 100%; border-radius: 999px; background: linear-gradient(90deg, #8B5E83, #E8735B); }' +
    '.gyeol-level { display: inline-block; padding: 14px 32px; border-radius: 999px; ' +
    '  background: #6B4460; color: #fbf3e9; font-size: 30px; font-weight: 700; margin-bottom: 20px; }' +
    '.gyeol-footer { position: absolute; left: 0; right: 0; bottom: 56px; text-align: center; }' +
    '.gyeol-footer-cta { font-size: 32px; font-weight: 700; color: #fff; margin-bottom: 10px; }' +
    '.gyeol-footer-brand { font-size: 24px; color: rgba(255,255,255,0.85); }';

  function decorBlobs() {
    return '' +
      '<div class="gyeol-blob" style="width:460px;height:460px;top:-160px;right:-140px;' +
      '  background:radial-gradient(circle, rgba(255,255,255,0.30), transparent 70%);"></div>' +
      '<div class="gyeol-blob" style="width:520px;height:520px;bottom:-200px;left:-160px;' +
      '  background:radial-gradient(circle, rgba(255,255,255,0.20), transparent 70%);"></div>' +
      '<span class="gyeol-sparkle" style="top:190px; left:96px;">✨</span>' +
      '<span class="gyeol-sparkle" style="top:170px; right:110px; font-size:30px;">✨</span>';
  }

  function buildSingleCardHTML(state) {
    var saju = state.saju, love = state.love;
    var typeKey = love.dayEl + '_' + love.dayYY;
    var type = TYPE_NAME[typeKey] || { name: love.base.style, emoji: '💕' };
    var dayLabel = saju.day.stem + saju.day.branch;
    var name = state.name ? escapeHtml(state.name) + '님은' : '나는';

    return '' +
      '<div class="gyeol-card">' +
        decorBlobs() +
        '<div class="gyeol-kicker">결 · AI 연애 사주</div>' +
        '<div class="gyeol-panel">' +
          '<div class="gyeol-emoji">' + type.emoji + '</div>' +
          '<div class="gyeol-tagline" style="margin-bottom:6px; font-weight:700;">' + name + '</div>' +
          '<div class="gyeol-type">' + escapeHtml(type.name) + '</div>' +
          '<div class="gyeol-tagline">' + escapeHtml(truncate(love.base.style, 46)) + '</div>' +
          '<div>' +
            '<span class="gyeol-chip">일주 ' + escapeHtml(dayLabel) + '</span>' +
            '<span class="gyeol-chip">오행 ' + escapeHtml(love.dayEl) + '</span>' +
          '</div>' +
        '</div>' +
        '<div class="gyeol-footer">' +
          '<div class="gyeol-footer-cta">너의 연애 유형은 뭘까? 👉</div>' +
          '<div class="gyeol-footer-brand">결 · 사주로 읽는 나의 연애 기질</div>' +
        '</div>' +
      '</div>';
  }

  function buildCompatCardHTML(state) {
    var compat = state.compat;
    var nameA = escapeHtml(state.nameA || 'A');
    var nameB = escapeHtml(state.nameB || 'B');
    var note = truncate(compat.notes[0] || '', 50);

    return '' +
      '<div class="gyeol-card">' +
        decorBlobs() +
        '<div class="gyeol-kicker">결 · 궁합 리포트</div>' +
        '<div class="gyeol-panel">' +
          '<div class="gyeol-names">' + nameA + ' 💘 ' + nameB + '</div>' +
          '<div class="gyeol-score-row">' +
            '<span class="gyeol-score">' + compat.score + '</span>' +
            '<span class="gyeol-score-unit">점</span>' +
          '</div>' +
          '<div class="gyeol-gauge-track"><div class="gyeol-gauge-fill" style="width:' + compat.score + '%;"></div></div>' +
          '<div class="gyeol-level">' + escapeHtml(compat.levelLabel) + '</div>' +
          '<div class="gyeol-tagline">' + escapeHtml(note) + '</div>' +
        '</div>' +
        '<div class="gyeol-footer">' +
          '<div class="gyeol-footer-cta">우리 궁합도 봐볼까? 👉</div>' +
          '<div class="gyeol-footer-brand">결 · 사주로 보는 우리의 궁합</div>' +
        '</div>' +
      '</div>';
  }

  function fontsReady() {
    if (!document.fonts || !document.fonts.ready) return Promise.resolve();
    return Promise.all([
      document.fonts.load('400 40px "Song Myung"'),
      document.fonts.load('700 40px "Gowun Dodum"'),
      document.fonts.load('400 60px "Black Han Sans"')
    ]).catch(function () {}).then(function () { return document.fonts.ready; });
  }

  // 화면 밖에 카드를 실제로 렌더링해서 html2canvas가 캡처할 수 있게 함(display:none이면 캡처 불가).
  function mountOffscreen(html) {
    var host = document.createElement('div');
    host.style.position = 'absolute';
    host.style.top = '0';
    host.style.left = '-10000px';
    host.innerHTML = '<style>' + CARD_CSS + '</style>' + html;
    document.body.appendChild(host);
    return host;
  }

  function renderShareCard(previewBox, kind, state) {
    previewBox.innerHTML = '';
    previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중…'));

    if (!window.html2canvas) {
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드 생성 기능을 불러오지 못했어요. 새로고침 후 다시 시도해 주세요.'));
      return;
    }

    var html = kind === 'compat' ? buildCompatCardHTML(state) : buildSingleCardHTML(state);

    fontsReady().then(function () {
      var host = mountOffscreen(html);
      var target = host.querySelector('.gyeol-card');

      return window.html2canvas(target, { backgroundColor: null, scale: 1, useCORS: true })
        .then(function (canvas) {
          document.body.removeChild(host);

          previewBox.innerHTML = '';
          var img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.alt = '공유 카드 미리보기';
          previewBox.appendChild(img);
          previewBox.appendChild(txt('div', 'hint', '이미지를 길게 눌러 저장하거나, 아래 버튼으로 저장/공유하세요.'));

          var actions = el('div', { class: 'share-card-actions' });

          var saveBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('이미지 저장')]);
          saveBtn.addEventListener('click', function () {
            canvas.toBlob(function (blob) {
              if (!blob) return;
              var url = URL.createObjectURL(blob);
              var a = document.createElement('a');
              a.href = url;
              a.download = 'gyeol-' + kind + '-card.png';
              document.body.appendChild(a);
              a.click();
              document.body.removeChild(a);
              setTimeout(function () { URL.revokeObjectURL(url); }, 4000);
            }, 'image/png');
          });
          actions.appendChild(saveBtn);

          if (navigator.share) {
            var shareBtn = el('button', { type: 'button', class: 'btn' }, [document.createTextNode('공유하기')]);
            shareBtn.addEventListener('click', function () {
              canvas.toBlob(function (blob) {
                if (!blob) return;
                var file = new File([blob], 'gyeol-' + kind + '-card.png', { type: 'image/png' });
                if (navigator.canShare && !navigator.canShare({ files: [file] })) return;
                navigator.share({ files: [file], title: '결 — 사주 공유 카드' }).catch(function () {});
              }, 'image/png');
            });
            actions.appendChild(shareBtn);
          }

          previewBox.appendChild(actions);
        });
    }).catch(function () {
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중 문제가 생겼어요. 다시 시도해 주세요.'));
    });
  }

  window.YeonbunReports = {
    attachSingleCTA: attachSingleCTA,
    attachCompatCTA: attachCompatCTA
  };
})();
