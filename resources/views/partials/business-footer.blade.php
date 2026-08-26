{{--
  전자상거래법상 통신판매업자 신원 정보 표시.

  (2026-08-26 수정) 사업자 정보를 항목별 줄바꿈 목록(회사이름 / 대표 - 대표자 /
  사업자등록번호 : ~~~ / 통신판매업번호 : ~~~)으로 정리하고, 개인정보 처리방침 링크와
  인스타그램 아이콘을 추가했다. 개인정보 처리방침은 사업자등록 여부와 무관하게(소셜
  로그인으로 이메일을 수집하는 시점부터) 항상 필요하므로, 기존처럼 BUSINESS_REG_NO
  전체를 감싸던 @if 밖으로 뺐다 — 사업자등록 전 테스트 단계에서도 링크는 계속 보인다.
  인스타그램 아이콘은 BUSINESS_INSTAGRAM이 비어있으면 다른 사업자 정보 항목들과 같은
  원칙으로 숨긴다.
--}}
<div class="business-footer">
  @if (config('business.reg_no'))
    @php
      $regNoDigits = preg_replace('/[^0-9]/', '', config('business.reg_no'));
    @endphp
    <ul class="business-footer-list">
      <li>{{ config('business.name') }}</li>
      @if (config('business.owner'))
        <li>대표 - {{ config('business.owner') }}</li>
      @endif
      <li>
        사업자등록번호 : {{ config('business.reg_no') }}
        @if ($regNoDigits)
          (<a href="https://www.ftc.go.kr/bizCommPop.do?wrkr_no={{ $regNoDigits }}" target="_blank" rel="noopener noreferrer">사업자정보 확인</a>)
        @endif
      </li>
      @if (config('business.mail_order_no'))
        <li>통신판매업번호 : {{ config('business.mail_order_no') }}</li>
      @endif
    </ul>
    @if (config('business.address') || config('business.phone') || config('business.email'))
      <p class="business-footer-contact">
        @if (config('business.address'))
          {{ config('business.address') }} ·
        @endif
        @if (config('business.phone'))
          {{ config('business.phone') }}
        @endif
        @if (config('business.email'))
          · {{ config('business.email') }}
        @endif
      </p>
    @endif
  @endif

  <div class="business-footer-links">
    <a href="{{ route('privacy.index') }}">개인정보 처리방침</a>
    @if (config('business.instagram'))
      <a class="business-footer-instagram" href="{{ config('business.instagram') }}" target="_blank" rel="noopener noreferrer" aria-label="인스타그램">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="3" width="18" height="18" rx="5.5"></rect>
          <circle cx="12" cy="12" r="4"></circle>
          <circle cx="17.3" cy="6.7" r="0.9" fill="currentColor" stroke="none"></circle>
        </svg>
      </a>
    @endif
  </div>
</div>
