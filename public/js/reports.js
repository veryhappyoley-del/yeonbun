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
    return { stem: p.stem, branch: p.branch, element: p.stemElement, label: p.label };
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
      loveCaution: love.base.caution
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
      renderShareCard(previewBox, {
        kind: 'single',
        title: (state.name ? state.name + '님' : '나') + '의 연애 사주',
        big: state.saju.day.stem + state.saju.day.branch,
        badge: state.love.dayEl,
        subtitle: truncate(state.love.base.style, 58)
      });
    }));
  }

  function attachCompatCTA(card, state) {
    var input = buildCompatInput(state);
    var title = (state.nameA || 'A') + ' × ' + (state.nameB || 'B') + ' 프리미엄 궁합 리포트';
    card.appendChild(buildCTA('compat', { input: input, title: title }, function (previewBox) {
      renderShareCard(previewBox, {
        kind: 'compat',
        title: (state.nameA || 'A') + ' × ' + (state.nameB || 'B'),
        big: String(state.compat.score),
        badge: '점',
        subtitle: truncate(state.compat.levelLabel + ' · ' + state.compat.notes[0], 58)
      });
    }));
  }

  function truncate(str, n) {
    if (!str) return '';
    return str.length > n ? str.slice(0, n - 1) + '…' : str;
  }

  /* ============================================================
   * 공유 카드 — 순수 클라이언트 <canvas> 렌더링, 서버 호출 없음
   * ============================================================ */

  var CARD_COLORS = {
    paper: '#ede6d6',
    paper2: '#e3dac4',
    ink: '#201d1a',
    inkSoft: '#57524a',
    seal: '#8B5E83',
    sealDeep: '#6B4460',
    gold: '#E8735B'
  };

  function wrapText(ctx, text, maxWidth) {
    var words = String(text).split(' ');
    var lines = [];
    var line = '';
    words.forEach(function (w) {
      var test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = w;
      } else {
        line = test;
      }
    });
    if (line) lines.push(line);
    return lines;
  }

  function drawCard(canvas, data) {
    var size = 1080;
    canvas.width = size;
    canvas.height = size;
    var ctx = canvas.getContext('2d');

    ctx.fillStyle = CARD_COLORS.paper;
    ctx.fillRect(0, 0, size, size);

    // 바깥 테두리
    ctx.strokeStyle = CARD_COLORS.seal;
    ctx.lineWidth = 6;
    ctx.strokeRect(48, 48, size - 96, size - 96);

    // 인장(도장) 마크
    var sealX = size / 2, sealY = 300, sealR = 84;
    ctx.strokeStyle = CARD_COLORS.seal;
    ctx.lineWidth = 5;
    ctx.beginPath();
    ctx.roundRect ? ctx.roundRect(sealX - sealR, sealY - sealR, sealR * 2, sealR * 2, 20) : ctx.rect(sealX - sealR, sealY - sealR, sealR * 2, sealR * 2);
    ctx.stroke();
    ctx.fillStyle = CARD_COLORS.seal;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.font = '400 92px "Song Myung", serif';
    ctx.fillText('결', sealX, sealY + 10);

    // 큰 숫자/글자(궁합 점수 또는 일주)
    ctx.fillStyle = CARD_COLORS.sealDeep;
    ctx.font = '400 150px "Song Myung", serif';
    ctx.fillText(data.big, sealX, 520);

    ctx.fillStyle = CARD_COLORS.inkSoft;
    ctx.font = '700 34px "Gowun Dodum", sans-serif';
    ctx.fillText(data.badge, sealX, 590);

    // 타이틀
    ctx.fillStyle = CARD_COLORS.ink;
    ctx.font = '700 52px "Gowun Dodum", sans-serif';
    ctx.fillText(data.title, sealX, 700);

    // 서브타이틀(여러 줄)
    ctx.fillStyle = CARD_COLORS.inkSoft;
    ctx.font = '400 32px "Gowun Dodum", sans-serif';
    var lines = wrapText(ctx, data.subtitle, size - 220);
    lines.slice(0, 3).forEach(function (line, i) {
      ctx.fillText(line, sealX, 760 + i * 44);
    });

    // 하단 브랜드
    ctx.fillStyle = CARD_COLORS.seal;
    ctx.font = '700 30px "Gowun Dodum", sans-serif';
    ctx.fillText('결 · 연애 특화 사주', sealX, size - 120);
    ctx.fillStyle = CARD_COLORS.inkSoft;
    ctx.font = '400 24px "Gowun Dodum", sans-serif';
    ctx.fillText('사주팔자로 읽는 나의 연애 기질과 궁합', sealX, size - 82);
  }

  function fontsReady() {
    if (!document.fonts || !document.fonts.ready) return Promise.resolve();
    return Promise.all([
      document.fonts.load('400 92px "Song Myung"'),
      document.fonts.load('700 34px "Gowun Dodum"')
    ]).catch(function () {}).then(function () { return document.fonts.ready; });
  }

  function renderShareCard(previewBox, data) {
    previewBox.innerHTML = '';
    previewBox.appendChild(txt('div', 'hint', '카드를 만드는 중…'));

    fontsReady().then(function () {
      var canvas = document.createElement('canvas');
      drawCard(canvas, data);

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
          a.download = 'gyeol-' + data.kind + '-card.png';
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
            var file = new File([blob], 'gyeol-' + data.kind + '-card.png', { type: 'image/png' });
            if (navigator.canShare && !navigator.canShare({ files: [file] })) return;
            navigator.share({ files: [file], title: '결 — 사주 공유 카드', text: data.title }).catch(function () {});
          }, 'image/png');
        });
        actions.appendChild(shareBtn);
      }

      previewBox.appendChild(actions);
    });
  }

  window.YeonbunReports = {
    attachSingleCTA: attachSingleCTA,
    attachCompatCTA: attachCompatCTA
  };
})();
