{{-- 전자상거래법상 통신판매업자 신원 정보 표시. BUSINESS_REG_NO가 .env에 없으면
     아무것도 렌더링하지 않습니다(사업자등록 전 테스트 단계 대비). --}}
@if (config('business.reg_no'))
  @php
    $regNoDigits = preg_replace('/[^0-9]/', '', config('business.reg_no'));
  @endphp
  <div class="business-footer">
    <p>
      {{ config('business.name') }}
      @if (config('business.owner'))
        · 대표 {{ config('business.owner') }}
      @endif
      · 사업자등록번호 {{ config('business.reg_no') }}
      @if ($regNoDigits)
        (<a href="https://www.ftc.go.kr/bizCommPop.do?wrkr_no={{ $regNoDigits }}" target="_blank" rel="noopener noreferrer">사업자정보 확인</a>)
      @endif
      @if (config('business.mail_order_no'))
        · 통신판매업신고 {{ config('business.mail_order_no') }}
      @endif
    </p>
    <p>
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
  </div>
@endif
