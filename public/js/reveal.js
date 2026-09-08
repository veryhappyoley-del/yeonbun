// (2026-09-08 신설) "사주풀이/리포트 결과가 너무 길어서 맨 밑까지 잘 안 내려본다"는
// 피드백 대응 — 이 파일 하나로 두 가지를 처리한다.
//
//   1) initReveal(container, selector) — container 안의 selector에 해당하는 요소들을
//      화면에 들어올 때마다 순서대로(살짝 시차를 두고) 페이드인+슬라이드업 시켜서
//      "글이 띡 하고 한번에 뜨는" 대신 폭포수처럼 이어지는 느낌을 준다.
//   2) initScrollHint(options) — 페이지에 스크롤할 내용이 충분히 남아 있을 때만 동동
//      뜨는 화살표를 보여주고, 바닥 근처에 가면 사라진다. 눌러도 스크롤된다.
//
// 계산기 결과(public/js/app.js)와 프리미엄 리포트(reports/show.blade.php) 양쪽에서
// 그대로 재사용한다 — 기존 마크업 구조를 바꾸지 않고 바깥에서 씌우는 방식이라 이미
// 만들어진 여러 화면에 최소한의 변경으로 적용할 수 있다.
(function () {
  'use strict';

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function initReveal(container, selector) {
    if (!container) return;
    var targets = selector ? container.querySelectorAll(selector) : [container];
    if (!targets || !targets.length) return;

    if (!('IntersectionObserver' in window) || prefersReducedMotion()) {
      targets.forEach(function (t) { t.classList.add('reveal-item', 'is-revealed'); });
      return;
    }

    var order = 0;
    targets.forEach(function (t) { t.classList.add('reveal-item'); });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var target = entry.target;
        io.unobserve(target);
        // 화면에 여러 개가 한꺼번에 걸쳐 들어와도 완전히 동시에 뜨지 않고 살짝씩
        // 늦춰서(최대 240ms) 폭포수처럼 이어지는 느낌을 준다.
        var delay = Math.min(order * 60, 240);
        order += 1;
        setTimeout(function () { target.classList.add('is-revealed'); }, delay);
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });

    targets.forEach(function (t) { io.observe(t); });
  }

  // (2026-09-08 추가) html2canvas로 캡처하는 기능들(PDF 저장/카드 공유 이미지)이 아직
  // 화면에 안 들어와서(reveal 전) opacity:0 상태인 요소를 그대로 찍어버리면 결과물이
  // 텅 비거나 밀려 보인다 — 캡처 직전에 해당 범위(기본은 문서 전체) 안의 모든
  // reveal-item을 트랜지션 없이 즉시 최종 상태로 만들어서 이 문제를 막는다.
  function revealAllNow(root) {
    var scope = root || document;
    var items = scope.querySelectorAll ? scope.querySelectorAll('.reveal-item:not(.is-revealed)') : [];
    if (!items.length) return;
    items.forEach(function (el) {
      el.style.transition = 'none';
      el.classList.add('is-revealed');
    });
    // 위에서 바꾼 스타일이 캡처 시점에 실제로 반영돼 있도록 강제로 리플로우시킨다.
    void document.body.offsetHeight;
  }

  function initScrollHint(options) {
    options = options || {};

    // 이미 만들어둔 화살표가 있으면 새로 만들지 않고 재사용(같은 페이지에서 계산기를
    // 다시 돌리는 등 여러 번 호출돼도 화살표가 중복 생기지 않게).
    var hint = document.querySelector('.scroll-hint');
    if (!hint) {
      hint = document.createElement('button');
      hint.type = 'button';
      hint.className = 'scroll-hint';
      hint.setAttribute('aria-label', '아래로 더 보기');
      hint.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"></path></svg>';
      hint.addEventListener('click', function () {
        window.scrollBy({ top: window.innerHeight * 0.75, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
      });
      document.body.appendChild(hint);
    }

    var update = function () {
      // 페이지(또는 지정된 컨테이너)가 화면 한 장 분량보다 충분히 길 때만 안내 자체가
      // 의미 있다 — 짧은 페이지에서까지 화살표가 뜨면 오히려 어색하다.
      var minHeight = options.minContentHeight || window.innerHeight * 1.15;
      if (document.documentElement.scrollHeight < minHeight) {
        hint.classList.remove('is-visible');
        return;
      }
      var scrollBottom = window.scrollY + window.innerHeight;
      var nearBottom = scrollBottom >= document.documentElement.scrollHeight - 56;
      hint.classList.toggle('is-visible', !nearBottom);
    };

    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    // 결과가 막 그려진 시점엔 레이아웃이 아직 자리잡는 중일 수 있어 한 박자 늦춰 재확인.
    update();
    setTimeout(update, 150);

    return hint;
  }

  window.YeonbunReveal = { init: initReveal, initScrollHint: initScrollHint, revealAllNow: revealAllNow };
})();
