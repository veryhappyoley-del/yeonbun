/**
 * "심층 리포트 / 프리미엄 궁합 리포트(유료)" 구매 버튼 + "공유 카드(무료)" 기능을
 * 사주풀이/궁합 결과 카드 아래에 붙여주는 스크립트.
 *
 * app.js가 결과를 렌더링한 직후 아래 함수들을 호출해서 이 스크립트에 결과 데이터를 넘겨줍니다.
 * - attachSingleCTA / attachCompatCTA: 결제 CTA. 결제는 billing.blade.php와 동일한
 *   토스페이먼츠 위젯 방식이고, 서버(ReportController)가 가격을 최종적으로 결정합니다.
 * - attachCardShare: "나의 연애 사주"의 연애 캐릭터 카드(love-character.js가 그린 실제
 *   .lc-card DOM)를 그대로 캡처해서 공유하는 기능. 궁합 카드는 여전히 별도의 HTML 템플릿
 *   (buildCompatCardHTML)으로 만듭니다. 둘 다 html2canvas 캡처라 서버 호출이 없어요(로그인 불필요).
 */
(function () {
  'use strict';

  // 화면 표시용 가격 안내일 뿐, 실제 결제 금액은 항상 서버(ReportController::TYPES)가 결정합니다.
  var TYPE_INFO = {
    single: { label: '심층 연애 리포트', priceLabel: '8,900원' },
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

  // 한 사람의 사주 요약(계산 결과 saju 객체 하나) — buildSingleInput의 pillars/dayElement/
  // dayYinYang/wuxingCount/deep과 동일한 모양으로 맞춰서, "궁합분석"의 챕터형 리포트가
  // 연애운분석과 같은 방식으로 각자의 deep(십신/지장간/합충형파해/신강신약/용신) 데이터를
  // 그대로 활용할 수 있게 한다.
  function personSummary(saju, name) {
    return {
      name: name || null,
      pillars: {
        year: pillarSummary(saju.year), month: pillarSummary(saju.month),
        day: pillarSummary(saju.day), hour: pillarSummary(saju.hour)
      },
      dayElement: saju.day.stemElement,
      dayYinYang: saju.day.stemYinYang,
      wuxingCount: saju.wuxingCount,
      deep: saju.deep || null
    };
  }

  // (예전 buildCompatInput) 원래는 두 사람의 일간(day)과 궁합 점수 요약만 보냈는데,
  // 그 정도 정보로는 20챕터짜리 "궁합분석" 프리미엄 리포트를 채울 재료가 부족해서
  // 두 사람 각자의 전체 deep 사주 데이터(calcSaju가 이미 계산해 둔 값, 별도 계산 불필요)를
  // 함께 보내도록 확장했다. 레거시 compat 타입의 ReportGenerator::compatPrompt()는
  // input의 특정 키에 의존하지 않고 JSON 전체를 그대로 프롬프트에 넣을 뿐이라, 이 변경이
  // 기존 프리미엄 궁합 리포트 생성 로직을 깨뜨리지 않는다(오히려 더 풍부한 근거를 준다).
  function buildTwoPersonInput(state) {
    var a = state.sajuA, b = state.sajuB, c = state.compat;
    return {
      personA: personSummary(a, state.nameA),
      personB: personSummary(b, state.nameB),
      score: c.score,
      levelLabel: c.levelLabel,
      notes: c.notes,
      relation: c.rel
    };
  }

  /* ============================================================
   * CTA(구매 버튼, 필요하면 공유카드 버튼도 함께) 렌더링
   * ============================================================ */

  // showShare: false면 공유 카드 버튼을 아예 안 그림 — "나의 연애 사주"는 이제 연애 캐릭터
  // 카드 자체를 공유하는 별도 버튼(attachCardShare)이 있어서 여기선 구매 버튼만 둠.
  function buildCTA(typeKey, buildTitle, onShare, opts) {
    opts = opts || {};
    var showShare = opts.showShare !== false;
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

    var previewBox = el('div', { class: 'share-preview' });
    if (showShare) {
      var shareBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('공유 카드 만들기')]);
      shareBtn.addEventListener('click', function () {
        onShare(previewBox);
      });
      row.appendChild(shareBtn);
    }

    wrap.appendChild(row);
    wrap.appendChild(statusBox);
    wrap.appendChild(previewBox);
    return wrap;
  }

  function attachSingleCTA(card, state) {
    var input = buildSingleInput(state);
    var title = (state.name ? state.name + '님의 ' : '') + '심층 연애 리포트';
    card.appendChild(buildCTA('single', { input: input, title: title }, null, { showShare: false }));
  }

  function attachCompatCTA(card, state) {
    var input = buildTwoPersonInput(state);
    var title = (state.nameA || 'A') + ' × ' + (state.nameB || 'B') + ' 프리미엄 궁합 리포트';
    card.appendChild(buildCTA('compat', { input: input, title: title }, function (previewBox) {
      renderCompatShareCard(previewBox, state);
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

  // 궁합 공유 카드용 HTML/CSS 템플릿. "나의 연애 사주" 쪽은 이제 이 템플릿을 쓰지 않고
  // 실제 연애 캐릭터 카드(.lc-card, love-character.js)를 그대로 캡처합니다 — 예전엔 여기 별도
  // TYPE_NAME 닉네임 매핑이 있었는데, love-character.js의 LOVE_CHARACTERS와 데이터가
  // 중복·불일치할 위험이 있어서 정리했습니다(캐릭터 카드가 유일한 유형명 출처가 됨).
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

  // 화면 밖에 HTML 문자열 카드를 실제로 렌더링해서 html2canvas가 캡처할 수 있게 함
  // (display:none이면 캡처 불가). 궁합 카드(buildCompatCardHTML)처럼 CARD_CSS를
  // 함께 주입해야 하는 "문자열로 만든" 카드용입니다.
  function mountOffscreen(html) {
    var host = document.createElement('div');
    host.style.position = 'absolute';
    host.style.top = '0';
    host.style.left = '-10000px';
    host.innerHTML = '<style>' + CARD_CSS + '</style>' + html;
    document.body.appendChild(host);
    return host;
  }

  function siteShareUrl() {
    if (window.YeonbunSite && window.YeonbunSite.url) return window.YeonbunSite.url;
    return (window.location && window.location.origin) || '';
  }

  function siteShareHost() {
    if (window.YeonbunSite && window.YeonbunSite.host) return window.YeonbunSite.host;
    try { return new URL(siteShareUrl()).host; } catch (e) { return '결'; }
  }

  // 캡처된 canvas를 미리보기 + "이미지 저장"/"공유하기" 버튼으로 보여주는 공통 로직.
  // shareMeta.url을 같이 넘기면(Web Share API가 지원하는 기기에서) 카카오톡/인스타 등으로
  // 공유할 때 이미지와 함께 사이트 링크도 실려서 "유입 유도" 역할을 함.
  function presentCanvasResult(canvas, previewBox, filename, shareMeta) {
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
        a.download = filename;
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
          var file = new File([blob], filename, { type: 'image/png' });
          var payload = { title: shareMeta.title, text: shareMeta.text, url: shareMeta.url };
          if (!navigator.canShare || navigator.canShare({ files: [file] })) {
            payload.files = [file];
          }
          // 파일 공유가 안 되는 브라우저에서도 문구+링크만이라도 공유되게(사이트 유입 유도).
          navigator.share(payload).catch(function () {});
        }, 'image/png');
      });
      actions.appendChild(shareBtn);
    }

    previewBox.appendChild(actions);
  }

  function renderCompatShareCard(previewBox, state) {
    previewBox.innerHTML = '';
    previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중…'));

    if (!window.html2canvas) {
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드 생성 기능을 불러오지 못했어요. 새로고침 후 다시 시도해 주세요.'));
      return;
    }

    var html = buildCompatCardHTML(state);

    fontsReady().then(function () {
      var host = mountOffscreen(html);
      var target = host.querySelector('.gyeol-card');

      return window.html2canvas(target, { backgroundColor: null, scale: 1, useCORS: true })
        .then(function (canvas) {
          document.body.removeChild(host);
          presentCanvasResult(canvas, previewBox, 'gyeol-compat-card.png', {
            title: '결 — 궁합 리포트 공유 카드',
            text: '우리 궁합 점수 나왔어! 너도 확인해볼래?',
            url: siteShareUrl()
          });
        });
    }).catch(function () {
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중 문제가 생겼어요. 다시 시도해 주세요.'));
    });
  }

  /* ============================================================
   * 연애 캐릭터 카드(love-character.js가 그린 .lc-card) 자체를 공유.
   * 별도 카드를 새로 만들지 않고 실제 온페이지 카드를 그대로 캡처해서(내용 이중 관리 방지),
   * 캡처본에만 사이트 유입 문구를 덧붙입니다(온페이지 카드는 그대로 둠).
   * ============================================================ */

  function attachCardShare(cardEl, container) {
    var row = el('div', { class: 'lc-share-row' });
    var shareBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('이 카드 공유하기')]);
    var previewBox = el('div', { class: 'share-preview' });

    shareBtn.addEventListener('click', function () {
      renderCharacterCardShare(cardEl, previewBox);
    });

    row.appendChild(shareBtn);
    row.appendChild(previewBox);
    container.appendChild(row);
  }

  // 화면에 실제로 그려진 카드(cardEl)를 그대로 캡처합니다 — 예전처럼 카드를 복제해서
  // 화면 밖 고정 폭(440px) 컨테이너에 다시 그리면, 실제 화면 폭(반응형 레이아웃에 따라
  // 다름)과 달라져서 "너비가 줄어들면서 볼품없어지는" 문제가 생겼습니다.
  // html2canvas의 onclone 옵션을 쓰면 html2canvas가 캡처용으로 만드는 내부 사본에만
  // 유입 문구 푸터를 추가할 수 있어서, 실제 페이지의 카드는 전혀 건드리지 않고도
  // (깜빡임 없이) 화면에 보이는 그대로의 크기/모양으로 캡처할 수 있습니다.
  function renderCharacterCardShare(cardEl, previewBox) {
    previewBox.innerHTML = '';
    previewBox.appendChild(txt('div', 'hint', '카드 이미지를 만드는 중…'));

    if (!window.html2canvas) {
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드 생성 기능을 불러오지 못했어요. 새로고침 후 다시 시도해 주세요.'));
      return;
    }

    // onclone 콜백 안에서 캡처 대상을 정확히 찾기 위한 임시 마커(캡처 후 원상복구).
    var markerAttr = 'data-lc-share-capture';
    var hadMarker = cardEl.hasAttribute(markerAttr);
    cardEl.setAttribute(markerAttr, '1');

    function restore() {
      if (!hadMarker) cardEl.removeAttribute(markerAttr);
    }

    fontsReady().then(function () {
      return window.html2canvas(cardEl, {
        backgroundColor: null,
        scale: 2,
        useCORS: true,
        onclone: function (clonedDoc) {
          var target = clonedDoc.querySelector('[' + markerAttr + ']');
          if (!target) return;
          target.style.animation = 'none'; // 리빌 애니메이션은 캡처 순간과 안 맞을 수 있어서 끔

          // 캡처본 전용 — "결" 유입을 유도하는 문구를 카드 하단에 덧붙임(실제 온페이지 카드는 안 건드림).
          var shareFooter = clonedDoc.createElement('div');
          shareFooter.className = 'lc-share-footer';
          var cta = clonedDoc.createElement('div');
          cta.className = 'lc-share-footer-cta';
          cta.textContent = '나도 내 연애 캐릭터 뽑아보기 👉';
          var hostLine = clonedDoc.createElement('div');
          hostLine.className = 'lc-share-footer-host';
          hostLine.textContent = siteShareHost();
          shareFooter.appendChild(cta);
          shareFooter.appendChild(hostLine);
          target.appendChild(shareFooter);
        }
      });
    }).then(function (canvas) {
      restore();
      presentCanvasResult(canvas, previewBox, 'gyeol-love-character-card.png', {
        title: '결 — 나의 연애 캐릭터 카드',
        text: '내 사주로 나온 연애 캐릭터 카드! 너도 확인해볼래?',
        url: siteShareUrl()
      });
    }).catch(function () {
      restore();
      previewBox.innerHTML = '';
      previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중 문제가 생겼어요. 다시 시도해 주세요.'));
    });
  }

  window.YeonbunReports = {
    attachSingleCTA: attachSingleCTA,
    attachCompatCTA: attachCompatCTA,
    attachCardShare: attachCardShare
  };
})();
