/**
 * "PDF로 저장" 버튼의 실제 동작 (2026-08-26 신설).
 *
 * 예전엔 window.print()로 브라우저 인쇄 대화상자를 띄우고, 사용자가 그 안에서 다시
 * "PDF로 저장"을 골라야 진짜 파일이 생겼다 — 사용자 피드백: "다운로드 버튼인데 인쇄
 * 대화상자가 뜨는 게 어색하다". 그래서 버튼을 누르면 곧장 .pdf 파일을 다운로드하도록
 * 바꿨다.
 *
 * 서버(dompdf 등)에서 직접 PDF를 만들지 않는 이유는 여전히 같다 — 이 사이트 콘텐츠가
 * 전부 한글이라, 서버 PDF 라이브러리에 한글 폰트 파일을 별도로 심어야 하는데 파일 하나만
 * 잘못돼도 리포트 전체가 네모(□)로 깨지는 치명적 실패 위험이 있다(claude/로드맵.md의
 * "PDF 저장 + 리포트함 리디자인" 절 참고). 대신 브라우저에 이미 로드된 실제 폰트(Song
 * Myung/Gowun Dodum)로 화면에 렌더링된 모습을 html2canvas로 그대로 캡처해서 jsPDF로
 * 이어 붙인다 — 캡처 방식이라 PDF 안 텍스트는 선택/복사가 안 되지만(공유 카드 기능과
 * 같은 트레이드오프), 화면과 다르게 보일 위험 자체가 없다.
 *
 * 리포트 하나(특히 20챕터짜리)를 통째로 한 번에 캡처하면 캔버스가 너무 커져서
 * (스케일 2배 기준 세로 수만 px) 일부 브라우저(특히 모바일 사파리)의 캔버스 크기 제한에
 * 걸릴 수 있다. 그래서 챕터/섹션 단위로 나눠서 각각 따로 캡처한 뒤, 그 결과를 A4 페이지
 * 흐름 위에 순서대로 이어 붙인다(섹션 하나가 페이지보다 짧으면 페이지 중간에도 이어서
 * 계속 쌓고, 페이지에 안 들어가는 만큼만 다음 페이지로 슬라이스해서 넘긴다).
 */
(function () {
  'use strict';

  function fontsReady() {
    if (!document.fonts || !document.fonts.ready) return Promise.resolve();
    return Promise.all([
      document.fonts.load('400 40px "Song Myung"'),
      document.fonts.load('400 16px "Gowun Dodum"'),
      document.fonts.load('700 16px "Gowun Dodum"')
    ]).catch(function () {}).then(function () { return document.fonts.ready; });
  }

  // 리포트 본문의 모양(챕터형 / 레거시 단건 / 레거시 궁합 HTML)에 따라 캡처할 조각들을
  // 고른다 — 이미 있는 마크업(.chapter-section, .rpt-section)을 그대로 활용해서 새
  // data 속성을 추가하지 않았다.
  function pickSections(root) {
    var sections = [];
    var header = document.getElementById('report-pdf-header');
    if (header) sections.push(header);

    var chapterSections = root.querySelectorAll('.chapter-section');
    if (chapterSections.length) {
      return sections.concat(Array.prototype.slice.call(chapterSections));
    }

    var rptSections = root.querySelectorAll('.rpt-section');
    if (rptSections.length) {
      return sections.concat(Array.prototype.slice.call(rptSections));
    }

    var body = root.querySelector('.report-body') || root;
    return sections.concat([body]);
  }

  // pdf 위에 캔버스를 순서대로 이어 붙이는 함수를 만들어서 반환한다(cursorY를 클로저에
  // 가둬서 섹션이 바뀔 때마다 이어지는 위치를 기억한다).
  function makeFlowWriter(pdf, marginPt) {
    var pageWidth = pdf.internal.pageSize.getWidth();
    var pageHeight = pdf.internal.pageSize.getHeight();
    var contentWidth = pageWidth - marginPt * 2;
    var cursorY = marginPt;

    return function addCanvas(canvas) {
      if (!canvas || !canvas.width || !canvas.height) return;

      var scaleRatio = contentWidth / canvas.width;
      var imgHeight = canvas.height * scaleRatio;
      var maxSectionHeight = pageHeight - marginPt * 2;

      // 섹션 자체가 한 페이지 안에 들어갈 크기인데 지금 남은 공간엔 안 들어가면,
      // 페이지 중간에서 챕터가 어색하게 잘리는 것보다 다음 페이지 맨 위에서 새로
      // 시작하는 쪽이 자연스럽다(완벽히 막지는 못하지만 대부분의 챕터는 한 페이지보다
      // 짧아서 이걸로 충분하다).
      var availableHeight = pageHeight - marginPt - cursorY;
      if (imgHeight <= maxSectionHeight && imgHeight > availableHeight + 0.5) {
        pdf.addPage();
        cursorY = marginPt;
        availableHeight = maxSectionHeight;
      }

      var srcYPx = 0; // canvas 원본 픽셀 기준으로 이미 그린 높이
      var remaining = imgHeight;

      while (remaining > 0.5) {
        availableHeight = pageHeight - marginPt - cursorY;
        var drawHeight = Math.min(availableHeight, remaining);
        var sliceHeightPx = Math.max(1, Math.round(drawHeight / scaleRatio));

        // jsPDF의 addImage는 소스 이미지를 잘라내는 옵션이 없어서, 이번 페이지에
        // 그릴 만큼만 별도의 작은 캔버스로 직접 슬라이스해서 넣는다.
        var slice = document.createElement('canvas');
        slice.width = canvas.width;
        slice.height = sliceHeightPx;
        slice.getContext('2d').drawImage(
          canvas,
          0, srcYPx, canvas.width, sliceHeightPx,
          0, 0, canvas.width, sliceHeightPx
        );

        pdf.addImage(slice.toDataURL('image/jpeg', 0.92), 'JPEG', marginPt, cursorY, contentWidth, drawHeight);

        cursorY += drawHeight;
        srcYPx += sliceHeightPx;
        remaining -= drawHeight;

        if (remaining > 0.5) {
          pdf.addPage();
          cursorY = marginPt;
        }
      }

      cursorY += 10; // 섹션 사이 여백
    };
  }

  /**
   * @param {Object} opts
   * @param {HTMLElement} [opts.button] - 눌린 버튼(있으면 진행 중 문구/비활성화 처리).
   * @param {string} [opts.rootSelector] - 캡처할 리포트 루트 컨테이너 선택자.
   * @param {string} [opts.filename] - 저장될 파일명(.pdf 포함).
   * @param {function(boolean)} [opts.onDone] - 성공(true)/실패(false) 콜백.
   */
  function download(opts) {
    opts = opts || {};
    var button = opts.button || null;
    var rootSelector = opts.rootSelector || '#report-pdf-root';
    var filename = opts.filename || '연록_리포트.pdf';
    var onDone = opts.onDone || function () {};

    var root = document.querySelector(rootSelector);
    var jsPDFCtor = window.jspdf && window.jspdf.jsPDF;

    if (!root || !window.html2canvas || !jsPDFCtor) {
      window.alert('PDF 생성 기능을 불러오지 못했어요. 인터넷 연결을 확인하고 새로고침 후 다시 시도해 주세요.');
      onDone(false);
      return;
    }

    var originalLabel = button ? button.textContent : null;
    if (button) {
      button.disabled = true;
      button.textContent = 'PDF 만드는 중…';
    }

    function restoreButton() {
      if (!button) return;
      button.disabled = false;
      button.textContent = originalLabel;
    }

    var sections = pickSections(root).filter(Boolean);
    var pdf = new jsPDFCtor({ unit: 'pt', format: 'a4' });
    var addCanvas = makeFlowWriter(pdf, 28);

    fontsReady().then(function () {
      return sections.reduce(function (chain, sectionEl) {
        return chain.then(function () {
          return window.html2canvas(sectionEl, {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true
          });
        }).then(addCanvas);
      }, Promise.resolve());
    }).then(function () {
      pdf.save(filename);
      restoreButton();
      onDone(true);
    }).catch(function (err) {
      if (window.console && console.error) console.error('PDF 생성 실패', err);
      window.alert('PDF를 만드는 중 문제가 생겼어요. 잠시 후 다시 시도해 주세요.');
      restoreButton();
      onDone(false);
    });
  }

  window.YeonbunReportPdf = { download: download };
})();
