/**
 * "심층 리포트 / 프리미엄 궁합 리포트(유료)" 구매 버튼 + "공유 카드(무료)" 기능을
 * 사주풀이/궁합 결과 카드 아래에 붙여주는 스크립트.
 *
 * app.js가 결과를 렌더링한 직후 아래 함수들을 호출해서 이 스크립트에 결과 데이터를 넘겨줍니다.
 * - attachSingleCTA / attachCompatCTA: 결제 CTA. 결제는 billing.blade.php와 동일한
 *   토스페이먼츠 위젯 방식이고, 서버(ReportController)가 가격을 최종적으로 결정합니다.
 * - attachCardShare: "나의 연애 사주"의 연애 캐릭터 카드(love-character.js) / "궁합 보기"의
 *   궁합 유형 카드(compat-character.js)가 그린 실제 .lc-card DOM을 그대로 캡처해서 공유하는
 *   기능. (2026-08-24부터는 궁합 쪽도 별도 HTML 템플릿 없이 이 방식 하나로 통일 — 화면에
 *   보이는 카드와 공유 이미지의 유형명/문구가 서로 어긋날 위험이 없어짐.) html2canvas
 *   캡처라 서버 호출이 없어요(로그인 불필요).
 * - buildTocPreview: 결제 전 목차 미리보기 DOM을 만듭니다. 보통 buildCTA() 안에서 자동으로
 *   붙지만(includeToc 옵션), 궁합분석처럼 CTA 카드보다 위(무료 티저 바로 아래)에 따로
 *   배치하고 싶을 때 이 함수를 직접 호출할 수 있게 내보냅니다.
 */
(function () {
  'use strict';

  // 화면 표시용 가격 안내일 뿐, 실제 결제 금액은 항상 서버(App\ReportTypes\ReportTypeRegistry)가
  // 결정합니다. 예전 single(심층 연애 리포트)/compat(프리미엄 궁합 리포트, 각각 13개 섹션 단일
  // 생성)은 챕터형 20챕터 상품인 love_fortune(연애운분석)/compatibility(궁합분석)로 대체됐습니다
  // — 새 구매는 이 두 타입키만 쓰고, 예전 타입키는 이미 구매한 고객의 리포트를 보여줄 때만
  // 서버 쪽(ReportController)에서 계속 인식합니다.
  // (2026-08-31 수정) 브랜드 개편 — 01.연애의 나침반/02.우리의 연애온도/03.짝사랑의 다음 장/
  // 04.다시, 우리 순서로 라벨을 바꿨다. 키(love_fortune/compatibility/unrequited_love/
  // reunion_strategy)는 그대로라 결제·리포트 조회 로직은 전혀 영향받지 않는다.
  var TYPE_INFO = {
    love_fortune: { label: '연애의 나침반', priceLabel: '27,000원' },
    compatibility: { label: '우리의 연애온도', priceLabel: '21,900원' },
    unrequited_love: { label: '짝사랑의 다음 장', priceLabel: '23,900원' },
    // (2026-08-31 추가) "다시, 우리" — App\ReportTypes\Definitions\ReunionStrategyReportType.
    reunion_strategy: { label: '다시, 우리', priceLabel: '25,900원' }
  };

  // 결제 전 "이걸 사면 뭘 받는지" 안내용 — 목차 미리보기(제목+티저, 잠금 아이콘)와 FAQ.
  // 목차 데이터는 saju.blade.php가 App\ReportTypes\ReportTypeRegistry에서 그대로 뽑아
  // window.YeonbunReportPreview로 내려준다(AI 호출 없이 정적 데이터라 비용이 안 든다).
  var REPORT_FAQ = [
    {
      q: '어떤 방식으로 리포트를 만드나요?',
      a: '고전 명리학 이론을 폭넓게 학습한 AI가 사주 명식(연월일시), 오행, 십신, 신강신약 같은 명리학 데이터를 바탕으로 20개의 주제로 나눠서 각각 따로 깊이 있게 분석해요. 하나의 글로 뭉뚱그리지 않고 챕터마다 독립적으로 생성해서 내용이 겹치지 않고 깊이도 유지돼요.'
    },
    {
      q: '생성하는 데 얼마나 걸리나요?',
      a: '결제 직후 여러 챕터가 동시에 생성을 시작해서, 보통 1~2분 안에 대부분 완료돼요. 화면에서 몇 개가 완료됐는지 실시간으로 확인할 수 있어요.'
    },
    {
      q: '나중에 다시 볼 수 있나요?',
      a: '네, "내 리포트함"에 그대로 저장돼서 추가 결제 없이 언제든 다시 열람할 수 있어요.'
    },
    {
      q: '일부 챕터만 생성에 실패하면 어떻게 되나요?',
      a: '가끔 일부 챕터만 생성에 실패할 수 있는데, 그 챕터만 따로 "다시 생성" 버튼으로 재시도할 수 있어요. 나머지 완성된 챕터는 그대로 보실 수 있으니 전체를 다시 기다릴 필요는 없어요.'
    },
    {
      q: '결제는 안전한가요?',
      a: '토스페이먼츠를 통해 결제되고, 카드 정보는 저희 서버에 저장되지 않아요.'
    }
  ];

  function pad2(n) { return n < 10 ? '0' + n : String(n); }

  function buildTocPreview(typeKey) {
    var previews = window.YeonbunReportPreview || [];
    var previewData = null;
    for (var i = 0; i < previews.length; i++) {
      if (previews[i].key === typeKey) { previewData = previews[i]; break; }
    }
    if (!previewData || !previewData.chapters || !previewData.chapters.length) return null;

    // (2026-08-24 수정) ReportType::$previewChapterKeys로 일부만 골라 보여주는 타입(예:
    // 궁합분석)은 "20개 챕터 중 12개 미리보기"처럼 전체 개수도 함께 알려준다. 전체를
    // 그대로 보여주는 타입(previewData.totalChapters === chapters.length)은 기존처럼
    // "챕터 N개 전체 보기"로 표시.
    var total = previewData.totalChapters || previewData.chapters.length;
    var summaryText = total > previewData.chapters.length
      ? '목차 미리보기 · 전체 ' + total + '개 챕터 중 ' + previewData.chapters.length + '개'
      : '목차 미리보기 · 챕터 ' + previewData.chapters.length + '개 전체 보기';

    var details = el('details', { class: 'report-toc-toggle' });
    details.appendChild(txt('summary', '', summaryText));

    var list = el('ul', { class: 'report-toc-list' });
    previewData.chapters.forEach(function (ch, idx) {
      var item = el('li', { class: 'report-toc-item' });
      item.appendChild(txt('span', 'report-toc-num', pad2(idx + 1)));

      var body = el('div', { class: 'report-toc-body' });
      body.appendChild(txt('div', 'report-toc-title', ch.title));
      if (ch.teaser) body.appendChild(txt('div', 'report-toc-teaser', ch.teaser));
      item.appendChild(body);

      var lock = txt('span', 'report-toc-lock', '🔒');
      lock.setAttribute('aria-hidden', 'true');
      item.appendChild(lock);

      list.appendChild(item);
    });
    details.appendChild(list);
    return details;
  }

  // (2026-08-24 추가) 결제 버튼 바로 아래 붙는 신뢰 배지 4칸 — 경쟁사(명사도)의 "4대 학파
  // 교차판독/6중 멀티 엔진 검증" 같은 과장된 방법론 문구 대신, 우리가 실제로 만들어서 검증한
  // 기능만 정직하게 나열한다(20단계: Tool Use 전환+적응형 재시도로 챕터 생성 신뢰성을 실제로
  // 개선했고, 21단계: 실패 챕터는 항상 재시도 가능, 리포트함에 영구 저장됨 — 전부 사실).
  var TRUST_BADGES = [
    { icon: '📖', head: '20개 챕터', desc: '주제별로 나눠 깊이 있게 분석해요' },
    { icon: '⏱️', head: '1~2분 완성', desc: '결제 즉시 여러 챕터가 동시에 생성돼요' },
    { icon: '🔄', head: '실패해도 안심', desc: '일부만 실패해도 그 챕터만 다시 생성돼요' },
    { icon: '♾️', head: '평생 소장', desc: '리포트함에 저장돼 언제든 다시 볼 수 있어요' }
  ];

  function buildTrustBadges() {
    var grid = el('div', { class: 'report-trust-grid' });
    TRUST_BADGES.forEach(function (b) {
      var item = el('div', { class: 'report-trust-item' });
      item.appendChild(txt('div', 'report-trust-icon', b.icon));
      item.appendChild(txt('div', 'report-trust-head', b.head));
      item.appendChild(txt('div', 'report-trust-desc', b.desc));
      grid.appendChild(item);
    });
    return grid;
  }

  function buildFaqSection() {
    var wrap = el('div', { class: 'report-faq' });
    wrap.appendChild(txt('div', 'report-faq-title', '결제 전 자주 묻는 질문'));
    REPORT_FAQ.forEach(function (item) {
      var details = el('details', { class: 'report-faq-item' });
      details.appendChild(txt('summary', '', item.q));
      details.appendChild(txt('p', '', item.a));
      wrap.appendChild(details);
    });
    return wrap;
  }

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

  // (2026-08-25 추가, 로드맵 1·2번) 결제 버튼을 눌렀는데 로그인이 안 돼 있으면 이 게이트가
  // 뜨는데, 예전엔 로그인 링크에 돌아올 위치 정보가 전혀 없어서(카카오/네이버 로그인 URL이
  // 고정값) 로그인 후 무조건 홈으로 튕겨나가 입력했던 사주/궁합 결과가 다 날아갔다. 지금
  // 보고 있는 탭(단일/궁합)을 읽어서 ?redirect=현재경로?tab=... 을 로그인 URL에 붙여주면,
  // SocialAuthController가 이 값을 세션에 저장했다가 로그인 콜백에서 같은 탭으로 돌려보낸다.
  function currentLoginRedirectPath() {
    var activeTabBtn = document.querySelector('.tab-btn.active');
    var tab = activeTabBtn ? activeTabBtn.getAttribute('data-tab') : null;
    var path = (window.location && window.location.pathname) || '/calculator';
    return tab ? (path + '?tab=' + encodeURIComponent(tab)) : path;
  }

  function withLoginRedirect(loginUrl) {
    if (!loginUrl) return loginUrl;
    var sep = loginUrl.indexOf('?') === -1 ? '?' : '&';
    return loginUrl + sep + 'redirect=' + encodeURIComponent(currentLoginRedirectPath());
  }

  function showLoginGate(statusBox) {
    statusBox.innerHTML = '';
    statusBox.appendChild(txt('div', 'hint', '로그인하면 프리미엄 리포트를 구매할 수 있어요.'));
    var row = el('div', { class: 'login-gate-inline' });
    row.appendChild(el('a', { class: 'social-btn kakao', href: withLoginRedirect(window.YeonbunBilling.kakaoLoginUrl) }, [document.createTextNode('카카오로 로그인')]));
    row.appendChild(el('a', { class: 'social-btn naver', href: withLoginRedirect(window.YeonbunBilling.naverLoginUrl) }, [document.createTextNode('네이버로 로그인')]));
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
      relation: c.rel,
      // (2026-08-24 추가) 궁합 폼의 "현재 관계"/"지금 가장 궁금한 것" 선택값(둘 다 선택
      // 사항이라 null일 수 있음) — CompatibilityReportType의 프롬프트가 이 값에 맞춰
      // 톤/시제와 강조할 챕터를 조정한다.
      relationshipStage: state.relationshipStage || null,
      primaryConcern: state.primaryConcern || null,
      concernDetail: state.concernDetail || null
    };
  }

  // (2026-08-31 추가) "짝사랑 탈출" — buildTwoPersonInput과 거의 같은 모양이지만
  // relationshipStage/primaryConcern/concernDetail(짝사랑 단계엔 안 맞는 개념) 대신
  // genderA와 timingCandidates(대운/세운 실제 계산 결과, public/js/luck-cycle.js)를
  // 담는다. app.js의 renderUnrequitedResult가 이미 계산해서 state에 실어 보낸다 —
  // 이 함수는 계산 자체를 하지 않고 모양만 맞춰서 넘긴다(계산 로직을 두 곳에 두지 않기 위함).
  function buildUnrequitedInput(state) {
    var a = state.sajuA, b = state.sajuB, c = state.compat;
    return {
      personA: personSummary(a, state.nameA),
      personB: personSummary(b, state.nameB),
      score: c.score,
      levelLabel: c.levelLabel,
      notes: c.notes,
      relation: c.rel,
      genderA: state.genderA || null,
      genderB: state.genderB || null,
      // 결정론적으로 미리 계산된 시기 후보(연도/월/점수/이유) — AI는 이 중에서만 골라야
      // 하고 새로 지어내면 안 된다(UnrequitedLoveReportType의 moving_timing 챕터 참고).
      timingCandidates: state.timingCandidates || []
    };
  }

  // (2026-08-31 추가) "다시, 우리"(재회 전략) — personA/personB + 궁합 요약까지는
  // buildTwoPersonInput/buildUnrequitedInput과 같지만, 이 리포트만의 두 가지가 더 있다:
  //   1) 이별 히스토리(교제기간/이별시점/이별주도자/이별사유) — App\ReportTypes\InputShape::
  //      TwoPersonWithHistory가 문서화하던 값을 이번에 처음 실제로 채운다.
  //   2) monthlyCalendar/topWindows — "재회 타이밍 캘린더" 챕터용으로 실제 계산된 12개월
  //      대운/세운 점수(public/js/luck-cycle.js의 monthlyCalendar()). moving_timing
  //      챕터와 같은 이유로, AI가 날짜/등급을 지어내지 못하게 여기서 결정론적으로 미리
  //      계산해서 넘긴다 — app.js의 renderReunionResult가 계산해서 state에 실어 보낸다.
  function buildReunionInput(state) {
    var a = state.sajuA, b = state.sajuB, c = state.compat;
    return {
      personA: personSummary(a, state.nameA),
      personB: personSummary(b, state.nameB),
      score: c.score,
      levelLabel: c.levelLabel,
      notes: c.notes,
      relation: c.rel,
      genderA: state.genderA || null,
      genderB: state.genderB || null,
      datingDuration: state.datingDuration || null,
      breakupTiming: state.breakupTiming || null,
      breakupInitiator: state.breakupInitiator || null,
      breakupReason: state.breakupReason || null,
      breakupReasonDetail: state.breakupReasonDetail || null,
      // 결정론적으로 계산된 값 — AI는 여기 없는 시기/등급을 새로 지어내면 안 된다
      // (App\ReportTypes\Definitions\ReunionStrategyReportType의 reunion_calendar 챕터 참고).
      monthlyCalendar: state.monthlyCalendar || [],
      topWindows: state.topWindows || []
    };
  }

  /* ============================================================
   * CTA(구매 버튼, 필요하면 공유카드 버튼도 함께) 렌더링
   * ============================================================ */

  // showShare: false면 공유 카드 버튼을 아예 안 그림 — "나의 연애 사주"/"궁합 보기" 둘 다
  // 이제 각자의 캐릭터/유형 카드 자체를 공유하는 별도 버튼(attachCardShare)이 있어서
  // 여기선 구매 버튼만 둠. includeToc: false면 이 함수 안에서 목차 미리보기를 렌더링하지
  // 않음 — 궁합분석은 24단계부터 목차 미리보기를 CTA 카드 안이 아니라 무료 티저 바로
  // 아래(더 이탈 지점에 가깝게)로 옮겨서 별도로 렌더링하므로, 여기서 중복으로 그리지 않게.
  function buildCTA(typeKey, buildTitle, onShare, opts) {
    opts = opts || {};
    var showShare = opts.showShare !== false;
    var includeToc = opts.includeToc !== false;
    var info = TYPE_INFO[typeKey];
    var wrap = el('div', { class: 'report-cta' });
    wrap.appendChild(txt('p', 'report-cta-headline', '더 깊은 해석이 궁금하다면?'));

    var row = el('div', { class: 'share-card-actions' });
    var statusBox = txt('div', 'hint', '');
    statusBox.style.width = '100%';
    statusBox.style.marginTop = '10px';
    statusBox.style.marginBottom = '0';

    // (2026-08-24 추가) 결제 버튼이 다른 버튼들과 똑같이 생겨서 눈에 잘 안 띈다는 피드백에
    // 대응해 btn-buy로 크기/그라데이션/은은한 펄스를 준 전용 스타일을 얹었다(app.css 참고).
    var buyBtn = el('button', { type: 'button', class: 'btn btn-buy' }, [document.createTextNode(info.label + ' 전체 보기 · ' + info.priceLabel)]);
    buyBtn.addEventListener('click', function () {
      startCheckout(typeKey, buildTitle.input, buildTitle.title, statusBox);
    });
    row.appendChild(buyBtn);
    wrap.appendChild(row);

    // 결제 버튼 바로 아래 신뢰 배지 4칸(실제로 만든 기능만 정직하게 나열).
    wrap.appendChild(buildTrustBadges());

    var previewBox = el('div', { class: 'share-preview' });
    if (showShare) {
      var shareBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('공유 카드 만들기')]);
      shareBtn.addEventListener('click', function () {
        onShare(previewBox);
      });
      row.appendChild(shareBtn);
    }

    wrap.appendChild(statusBox);
    wrap.appendChild(previewBox);

    // 결제 전 "뭘 받는지" 미리 보여줘서 망설임을 줄이는 목차 미리보기(includeToc가 false가
    // 아닐 때만 — 궁합분석/연애운분석 둘 다 이 함수 밖(app.js)에서 무료 티저 바로 아래에
    // 별도로 렌더링하므로 여기선 건너뜀) + FAQ.
    // 콘텐츠 자체는 공개하지 않고 챕터 제목/티저(정적 데이터)와 자주 묻는 질문만 보여준다.
    if (includeToc) {
      var toc = buildTocPreview(typeKey);
      if (toc) wrap.appendChild(toc);
    }
    wrap.appendChild(buildFaqSection());

    return wrap;
  }

  // (2026-08-25 수정) 궁합분석과 같은 결제 유도 경험(무료 티저 → 목차 미리보기 → CTA)을
  // 쓰도록 includeToc:false로 바꿨다 — 목차 미리보기는 이제 app.js의 renderSingleResult가
  // 무료 티저(origin_profile) 바로 아래에서 별도로 렌더링한다.
  function attachSingleCTA(card, state) {
    var input = buildSingleInput(state);
    var title = (state.name ? state.name + '님의 ' : '') + '연애의 나침반';
    card.appendChild(buildCTA('love_fortune', { input: input, title: title }, null, { showShare: false, includeToc: false }));
  }

  // (2026-08-24 수정) 궁합분석의 공유는 이제 별도 HTML 템플릿(buildCompatCardHTML,
  // renderCompatShareCard)이 아니라 "궁합 유형 카드"(compat-character.js, app.js가
  // renderCompatResult()에서 이미 attachCardShare로 붙임) 자체를 캡처해서 씀 — "나의 연애
  // 사주"가 연애 캐릭터 카드 자체를 캡처하는 방식으로 이미 정리된 것과 같은 이유(유형명/
  // 문구가 화면에 보이는 카드와 공유 이미지에서 서로 어긋날 위험을 없앰). 그래서 여기서는
  // 일반 "공유 카드 만들기" 버튼을 안 그리고(showShare:false), 목차 미리보기도 이 함수 밖
  // (app.js)에서 무료 티저 바로 아래에 별도로 렌더링하므로 includeToc:false.
  function attachCompatCTA(card, state) {
    var input = buildTwoPersonInput(state);
    var title = (state.nameA || 'A') + ' × ' + (state.nameB || 'B') + ' 연애온도';
    card.appendChild(buildCTA('compatibility', { input: input, title: title }, null, { showShare: false, includeToc: false }));
  }

  // (2026-08-31 추가) "짝사랑 탈출" — attachCompatCTA와 같은 틀(무료 티저 → 목차 미리보기
  // → 구매 버튼)을 그대로 쓰되 typeKey/제목만 다르다.
  function attachUnrequitedCTA(card, state) {
    var input = buildUnrequitedInput(state);
    var title = (state.nameA || '나') + '님의 ' + (state.nameB || '그 사람') + ' 짝사랑의 다음 장';
    card.appendChild(buildCTA('unrequited_love', { input: input, title: title }, null, { showShare: false, includeToc: false }));
  }

  // (2026-08-31 추가) "다시, 우리" — attachUnrequitedCTA와 같은 틀, typeKey/제목/input만 다르다.
  function attachReunionCTA(card, state) {
    var input = buildReunionInput(state);
    var title = (state.nameA || '나') + ' × ' + (state.nameB || '그 사람') + ' 다시, 우리';
    card.appendChild(buildCTA('reunion_strategy', { input: input, title: title }, null, { showShare: false, includeToc: false }));
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
   * 공유 카드 — 화면에 실제로 그려진 카드(.lc-card)를 html2canvas로 그대로 이미지 캡처.
   * 서버 호출이 없어서 로그인하지 않아도 만들 수 있어요(바이럴 유입용).
   * ============================================================ */

  // (2026-08-24 수정) 카드 타이틀 폰트가 Black Han Sans → Song Myung으로 바뀌면서(사이트
  // 결에 맞춘 디자인 개정) 캡처 전에 미리 로드해둘 폰트 목록에서도 뺐다.
  function fontsReady() {
    if (!document.fonts || !document.fonts.ready) return Promise.resolve();
    return Promise.all([
      document.fonts.load('400 40px "Song Myung"'),
      document.fonts.load('700 40px "Gowun Dodum"')
    ]).catch(function () {}).then(function () { return document.fonts.ready; });
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

  /* ============================================================
   * 연애 캐릭터 카드(love-character.js) / 궁합 유형 카드(compat-character.js)가 그린
   * .lc-card 자체를 공유. 별도 카드를 새로 만들지 않고 실제 온페이지 카드를 그대로
   * 캡처해서(내용 이중 관리 방지), 캡처본에만 사이트 유입 문구를 덧붙입니다(온페이지
   * 카드는 그대로 둠).
   * ============================================================ */

  // meta(선택): { footerCta, filename, title, text } — 연애 캐릭터 카드/궁합 유형 카드처럼
  // 같은 캡처 로직을 쓰지만 카드 종류마다 다른 문구를 쓰고 싶을 때. 생략하면 기존(연애
  // 캐릭터 카드) 기본값을 그대로 씁니다.
  function attachCardShare(cardEl, container, meta) {
    var row = el('div', { class: 'lc-share-row' });
    var shareBtn = el('button', { type: 'button', class: 'btn outline' }, [document.createTextNode('이 카드 공유하기')]);
    var previewBox = el('div', { class: 'share-preview' });

    shareBtn.addEventListener('click', function () {
      renderCharacterCardShare(cardEl, previewBox, meta);
    });

    row.appendChild(shareBtn);
    row.appendChild(previewBox);
    container.appendChild(row);
  }

  var DEFAULT_SHARE_META = {
    footerCta: '나도 내 연애 캐릭터 뽑아보기 👉',
    filename: 'gyeol-love-character-card.png',
    title: '결 — 나의 연애 캐릭터 카드',
    text: '내 사주로 나온 연애 캐릭터 카드! 너도 확인해볼래?'
  };

  // 화면에 실제로 그려진 카드(cardEl)를 그대로 캡처합니다 — 예전처럼 카드를 복제해서
  // 화면 밖 고정 폭(440px) 컨테이너에 다시 그리면, 실제 화면 폭(반응형 레이아웃에 따라
  // 다름)과 달라져서 "너비가 줄어들면서 볼품없어지는" 문제가 생겼습니다.
  // html2canvas의 onclone 옵션을 쓰면 html2canvas가 캡처용으로 만드는 내부 사본에만
  // 유입 문구 푸터를 추가할 수 있어서, 실제 페이지의 카드는 전혀 건드리지 않고도
  // (깜빡임 없이) 화면에 보이는 그대로의 크기/모양으로 캡처할 수 있습니다.
  function renderCharacterCardShare(cardEl, previewBox, meta) {
    meta = meta || DEFAULT_SHARE_META;
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
          cta.textContent = meta.footerCta;
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
      presentCanvasResult(canvas, previewBox, meta.filename, {
        title: meta.title,
        text: meta.text,
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
    attachUnrequitedCTA: attachUnrequitedCTA,
    attachReunionCTA: attachReunionCTA,
    attachCardShare: attachCardShare,
    buildTocPreview: buildTocPreview,
    // (2026-08-25 추가) app.js의 renderSingleResult가 무료 티저(origin_profile)를 요청할 때
    // 결제용과 똑같은 input을 쓰기 위해 노출 — 같은 input이어야 ChapterGenerator의 입력
    // 해시가 일치해서 미리 본 내용이 결제 후 그대로 이어진다(궁합분석과 같은 보장).
    buildSingleInput: buildSingleInput
  };
})();
