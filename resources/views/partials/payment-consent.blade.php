{{--
  결제 전 필수 동의 (2026-09-28 신설).

  배경: 출시전 점검리스트 1항 — 코인 충전·리포트 결제 어디에도 "이용약관/환불정책에
  동의합니다" 체크가 없었다. 디지털 콘텐츠는 제공이 개시되면 청약철회가 제한될 수
  있는데, 그 제한을 주장하려면 결제 전에 그 사실을 고지하고 동의를 받아야 한다.

  파라미터
    $id       : 체크박스 id (한 화면에 두 개 이상 둘 때 구분용, 기본 payment-consent)
    $targets  : 이 체크박스가 잠글 버튼들의 CSS 선택자 (public/js/consent.js가 읽는다)

  동작은 public/js/consent.js가 담당한다 — 체크 전에는 대상 버튼이 disabled다.
  JS가 로드되지 않은 상태에서 결제가 진행되는 일이 없도록, 대상 버튼에는 마크업
  단계에서부터 disabled를 걸어 두는 것을 원칙으로 한다.
--}}
<div class="check-row consent-row">
  <input type="checkbox" id="{{ $id ?? 'payment-consent' }}" data-consent-targets="{{ $targets }}">
  <label for="{{ $id ?? 'payment-consent' }}">
    <strong>[필수]</strong>
    <a href="{{ route('terms.index') }}#refund" target="_blank" rel="noopener noreferrer">이용약관·환불정책</a>을
    확인했으며, 디지털 콘텐츠는 제공이 시작되면 청약철회가 제한된다는 점에 동의합니다.
  </label>
</div>
