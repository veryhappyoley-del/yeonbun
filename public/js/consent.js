/**
 * 결제 전 필수 동의 체크박스 (2026-09-28 신설).
 *
 * data-consent-targets="<CSS 선택자>"가 붙은 체크박스를 찾아, 체크되기 전까지
 * 그 선택자에 해당하는 버튼을 disabled로 잠근다. 결제 버튼이 Blade가 아니라
 * JS로 그려지는 화면(계산기 결과의 리포트 결제 CTA)에서도 같은 규칙을 쓰려고
 * init(root)를 밖으로 열어 둔다 — public/js/reports.js가 CTA를 만든 직후 호출한다.
 */
(function () {
  'use strict';

  function wire(box) {
    var selector = box.getAttribute('data-consent-targets');
    if (!selector || box.dataset.consentWired === '1') return;
    box.dataset.consentWired = '1';

    // 대상 버튼을 어디서 찾을지: 같은 카드 안으로 범위를 좁힐 수 있으면 그렇게 한다
    // (한 화면에 결제 카드가 여러 개 있어도 서로 간섭하지 않게).
    var scope = box.closest('[data-consent-scope]') || document;

    var apply = function () {
      var on = box.checked;
      scope.querySelectorAll(selector).forEach(function (btn) {
        btn.disabled = !on;
        btn.setAttribute('aria-disabled', on ? 'false' : 'true');
      });
    };

    box.addEventListener('change', apply);
    apply();
  }

  function init(root) {
    (root || document).querySelectorAll('[data-consent-targets]').forEach(wire);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }

  window.YeonbunConsent = { init: init };
})();
