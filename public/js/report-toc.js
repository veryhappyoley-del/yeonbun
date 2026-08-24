/**
 * 챕터형(schema_version=2) 리포트 화면(chapter-toc.blade.php + chapter-reader.blade.php)의
 * 클라이언트 동작 두 가지를 담당합니다:
 *
 * 1) 스크롤에 따라 현재 보고 있는 챕터의 목차 탭을 강조(IntersectionObserver).
 *    탭 클릭 → 해당 섹션으로 스크롤은 별도 JS 없이 앵커(#chapter-...) + CSS의
 *    scroll-behavior:smooth로 처리됩니다.
 * 2) 챕터 하나가 생성 실패했을 때(status=failed) "다시 생성하기" 버튼 클릭 시
 *    /reports/{report}/chapters/{chapterKey}/regenerate로 재시도 요청을 보내고,
 *    완료될 때까지 /reports/{report}/status를 폴링하다가 준비되면 새로고침합니다.
 *
 * public/js/reports.js/chat.js와 동일하게 순수 ES5 스타일(var/function)로 작성해서
 * 별도 빌드 도구 없이 그대로 <script src>로 로드합니다.
 */
(function () {
  'use strict';

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function setActiveTab(key) {
    var tabs = document.querySelectorAll('[data-chapter-tab]');
    for (var i = 0; i < tabs.length; i += 1) {
      var isActive = tabs[i].getAttribute('data-chapter-tab') === key;
      tabs[i].classList.toggle('active', isActive);
    }
  }

  function setupScrollSpy() {
    var sections = document.querySelectorAll('[data-chapter-section]');

    if (!sections.length || !('IntersectionObserver' in window)) {
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      for (var i = 0; i < entries.length; i += 1) {
        if (entries[i].isIntersecting) {
          setActiveTab(entries[i].target.getAttribute('data-chapter-section'));
        }
      }
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });

    for (var i = 0; i < sections.length; i += 1) {
      observer.observe(sections[i]);
    }
  }

  function pollChapterUntilReady(statusUrl, chapterKey, btn) {
    var attempts = 0;
    var maxAttempts = 40; // 약 2분(3초 간격) — 챕터 하나는 레거시 단일 호출보다 훨씬 짧음

    function tick() {
      attempts += 1;

      fetch(statusUrl, { headers: { Accept: 'application/json' } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          var chapters = data.chapters || [];
          var found = null;

          for (var i = 0; i < chapters.length; i += 1) {
            if (chapters[i].key === chapterKey) {
              found = chapters[i];
              break;
            }
          }

          if (found && found.status === 'ready') {
            window.location.reload();
            return;
          }

          if (found && found.status === 'failed' && attempts > 1) {
            // 재시도했는데도 다시 실패 — 버튼을 원상복구해서 사용자가 한 번 더 시도할 수 있게.
            btn.disabled = false;
            btn.textContent = '다시 생성하기';
            return;
          }

          if (attempts < maxAttempts) {
            setTimeout(tick, 3000);
          } else {
            btn.disabled = false;
            btn.textContent = '다시 생성하기';
          }
        })
        .catch(function () {
          if (attempts < maxAttempts) {
            setTimeout(tick, 3000);
          } else {
            btn.disabled = false;
            btn.textContent = '다시 생성하기';
          }
        });
    }

    tick();
  }

  function setupChapterRetryButtons() {
    var tocRoot = document.getElementById('chapter-toc');
    var statusUrl = tocRoot ? tocRoot.getAttribute('data-status-url') : null;
    var buttons = document.querySelectorAll('[data-chapter-regenerate]');

    for (var i = 0; i < buttons.length; i += 1) {
      (function (btn) {
        btn.addEventListener('click', function () {
          var url = btn.getAttribute('data-chapter-regenerate');
          var chapterKey = btn.getAttribute('data-chapter-key');

          if (!url) return;

          btn.disabled = true;
          btn.textContent = '재시도 요청 중…';

          fetch(url, {
            method: 'POST',
            headers: {
              'X-CSRF-TOKEN': csrfToken(),
              Accept: 'application/json'
            }
          })
            .then(function (res) {
              if (!res.ok) throw new Error('regenerate request failed');
              return res.json();
            })
            .then(function () {
              btn.textContent = '생성 중…';

              if (statusUrl && chapterKey) {
                pollChapterUntilReady(statusUrl, chapterKey, btn);
              } else {
                btn.disabled = false;
                btn.textContent = '다시 생성하기';
              }
            })
            .catch(function () {
              btn.disabled = false;
              btn.textContent = '다시 생성하기';
            });
        });
      })(buttons[i]);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    setupScrollSpy();
    setupChapterRetryButtons();
  });
})();
