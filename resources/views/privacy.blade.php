{{--
  개인정보 처리방침 (2026-08-26 신설) — 푸터(partials.business-footer)에서 항상 링크되는
  정적 공개 페이지. 소셜 로그인(카카오/네이버)으로 이메일을 수집하는 시점부터 필요한
  문서라 사업자등록 완료 여부와 무관하게 항상 접근 가능해야 한다(routes/web.php 참고).

  실제 수집 항목/저장 위치는 코드를 근거로 작성했다:
  - users 테이블: name, email, provider/provider_id(카카오·네이버), credits
  - chat_sessions.saju_context / chat_messages: AI 연애 코치 대화(사주 요약 + 대화 내용)
  - reports.input / reports.content: 프리미엄 리포트 생성용 사주·궁합 입력값 + AI 생성 결과
  - payments / reports (status, payment_key, order_id, amount): 토스페이먼츠 결제 내역
    (카드번호 등 민감한 결제수단 정보 자체는 토스페이먼츠가 처리하고 연록 서버에는 저장되지 않음)
  - page_views + yeonbun_visitor 쿠키(TrackPageView 미들웨어, 1년): 방문 통계
  - sessions 테이블: ip_address, user_agent(로그인 세션 유지용)
  - AI 응답 생성을 위해 사주/궁합 입력값과 대화 내용이 AI 모델 API 제공업체(해외 사업자)로
    전송됨 — 국외 이전 관련 문구 포함.

  이 문서는 실제 법률 검토를 대체하지 않는 초안이다. 사업자등록증이 나오고 실제 서비스
  운영 형태(위탁업체, 보관 기간 정책 등)가 확정되면 아래 내용, 특히 위탁/국외이전/보유기간
  섹션을 실제 계약 내용에 맞게 다시 확인하는 것을 권장한다(채팅에서 사용자에게 안내함).
--}}
@php
  $bizName = config('business.name') ?: '연록 서비스 운영자';
  $bizOwner = config('business.owner');
  $bizEmail = config('business.email');
  $bizPhone = config('business.phone');
  $bizAddress = config('business.address');
@endphp
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>개인정보 처리방침 — 연록</title>
  <meta name="description" content="연록 서비스의 개인정보 수집·이용·보관에 관한 안내입니다.">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>개인정보 처리방침</h1>
      <p>연록이 어떤 정보를 왜 모으고, 어떻게 보관·이용하는지 안내드려요.</p>
    </div>
  </div>

  <div class="card">
    <p class="policy-meta">시행일자 : 2026년 8월 26일</p>

    <div class="policy-section">
      <h3>1. 총칙</h3>
      <p>
        {{ $bizName }}(이하 "회사")는 이용자의 개인정보를 중요시하며, 「개인정보 보호법」 등
        관련 법령을 준수합니다. 회사는 본 개인정보 처리방침을 통해 이용자가 제공하는 개인정보가
        어떤 목적과 방식으로 이용되고 있으며, 개인정보 보호를 위해 어떤 조치가 취해지고 있는지
        알려드립니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>2. 수집하는 개인정보 항목 및 수집 방법</h3>
      <p>회사는 서비스 제공을 위해 다음과 같은 개인정보를 수집합니다.</p>
      <div style="overflow-x:auto;">
        <table class="policy-table">
          <thead>
            <tr><th>구분</th><th>수집 항목</th><th>수집 방법</th></tr>
          </thead>
          <tbody>
            <tr>
              <td>소셜 로그인(카카오/네이버)</td>
              <td>이메일, 소셜 계정 식별자(제공업체·고유ID), 닉네임</td>
              <td>카카오/네이버 로그인 시 해당 사업자로부터 제공받음</td>
            </tr>
            <tr>
              <td>사주 계산·해석 정보</td>
              <td>생년월일시, 별칭(이름), 성별(해당 기능 이용 시), 출생 지역</td>
              <td>이용자가 계산기/연애 코치/리포트 신청 화면에 직접 입력</td>
            </tr>
            <tr>
              <td>AI 연애 코치 이용 기록</td>
              <td>대화 내용, 사주 요약 컨텍스트</td>
              <td>연애 코치 대화 이용 시 자동 저장</td>
            </tr>
            <tr>
              <td>프리미엄 리포트 이용 기록</td>
              <td>리포트 생성에 사용한 입력값(본인·상대방 사주 정보), 생성된 리포트 본문</td>
              <td>연애운분석·궁합분석 등 리포트 결제·생성 시 저장</td>
            </tr>
            <tr>
              <td>결제 정보</td>
              <td>주문번호, 결제금액, 결제수단 구분, 결제 상태</td>
              <td>토스페이먼츠 결제창을 통한 결제 시 생성</td>
            </tr>
            <tr>
              <td>서비스 이용 기록</td>
              <td>방문 페이지, 방문자 식별 쿠키, 접속 IP, 브라우저(User-Agent) 정보</td>
              <td>서비스 접속 시 자동 수집</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p>
        신용카드 번호, 계좌번호 등 결제수단 자체의 민감한 정보는 회사 서버에 저장되지 않으며,
        결제대행사(토스페이먼츠)가 직접 수집·처리합니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>3. 개인정보의 수집 및 이용 목적</h3>
      <ol>
        <li>회원 식별 및 카카오·네이버 소셜 로그인을 통한 서비스 이용</li>
        <li>사주·궁합 계산 결과 및 AI 기반 해석 콘텐츠(연애 코치, 프리미엄 리포트) 제공</li>
        <li>코인 충전·리포트 결제 처리, 결제 내역 확인 및 분쟁 대응</li>
        <li>서비스 부정 이용 방지, 접속 통계 분석을 통한 서비스 개선</li>
        <li>고객 문의 응대</li>
      </ol>
    </div>

    <div class="policy-section">
      <h3>4. 개인정보의 보유 및 이용 기간</h3>
      <p>
        회사는 원칙적으로 개인정보 수집·이용 목적이 달성되거나 이용자가 회원 탈퇴를 요청하면
        해당 정보를 지체 없이 파기합니다. 다만 다음 정보는 명시된 사유로 아래 기간 동안 보관합니다.
      </p>
      <ul>
        <li>「전자상거래 등에서의 소비자보호에 관한 법률」에 따른 대금결제 및 재화 등의 공급에 관한 기록 : 5년</li>
        <li>같은 법률에 따른 소비자 불만 또는 분쟁처리에 관한 기록 : 3년</li>
        <li>「통신비밀보호법」에 따른 서비스 이용 관련 접속 기록(로그인 기록 등) : 3개월</li>
        <li>결제한 프리미엄 리포트 본문 및 결제 내역 : 구매 이력 조회 편의를 위해 회원 탈퇴 전까지 보관(탈퇴 시 파기)</li>
      </ul>
    </div>

    <div class="policy-section">
      <h3>5. 개인정보의 제3자 제공 및 처리위탁, 국외이전</h3>
      <p>
        회사는 이용자의 개인정보를 원칙적으로 외부에 제공하지 않습니다. 다만 서비스 제공을
        위해 아래와 같이 최소한의 범위에서 외부 업체에 정보 처리를 위탁하거나 제공합니다.
      </p>
      <ul>
        <li><strong>카카오, 네이버</strong> — 소셜 로그인 인증(회사는 이메일·식별자만 전달받으며, 비밀번호 등 로그인 정보 자체에는 접근하지 않습니다)</li>
        <li><strong>토스페이먼츠</strong> — 코인 충전·리포트 결제의 승인 및 처리</li>
        <li><strong>AI 모델 API 제공업체(해외 사업자)</strong> — 연애 코치 답변, 프리미엄 리포트 본문 등 AI 생성 콘텐츠를 만들기 위해, 입력하신 사주·궁합 정보와 대화 내용이 암호화된 통신으로 전송됩니다. 전송된 정보는 응답 생성 목적으로만 처리되며, 국외 이전이 발생할 수 있습니다.</li>
      </ul>
      <p>
        법령에 특별한 규정이 있거나 수사기관이 적법한 절차에 따라 요청하는 경우를 제외하고,
        위 목적 외로 개인정보를 제3자에게 제공하지 않습니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>6. 이용자의 권리와 행사 방법</h3>
      <p>
        이용자는 언제든지 자신의 개인정보 열람·정정·삭제·처리정지를 요청할 수 있으며,
        마이페이지 또는 아래 문의처를 통해 회원 탈퇴(개인정보 삭제)를 요청할 수 있습니다.
        회사는 관련 법령에 따른 보유 의무가 있는 정보를 제외하고 지체 없이 조치합니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>7. 쿠키의 운영 및 거부</h3>
      <p>
        회사는 방문자 수 통계 등 서비스 개선을 위해 쿠키(yeonbun_visitor, 보관기간 1년)를
        사용합니다. 이용자는 브라우저 설정을 통해 쿠키 저장을 거부할 수 있으나, 이 경우 일부
        서비스 이용에 제한이 있을 수 있습니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>8. 개인정보의 안전성 확보조치</h3>
      <p>
        회사는 개인정보에 대한 접근 권한을 최소한의 인원으로 제한하고, 비밀번호 없이 소셜
        로그인만으로 인증하는 구조를 사용하며, 결제수단 정보는 자체 저장하지 않는 등 개인정보
        보호를 위한 기술적·관리적 조치를 취하고 있습니다.
      </p>
    </div>

    <div class="policy-section">
      <h3>9. 개인정보 보호책임자</h3>
      <p>
        회사는 개인정보 처리에 관한 업무를 총괄하는 개인정보 보호책임자를 지정하고 있습니다.
        개인정보와 관련한 문의사항은 아래 연락처로 연락해 주시기 바랍니다.
      </p>
      <ul>
        @if ($bizOwner)
          <li>담당자 : {{ $bizOwner }}</li>
        @endif
        <li>이메일 : {{ $bizEmail ?: '(사업자 정보 등록 후 안내 예정)' }}</li>
        @if ($bizPhone)
          <li>연락처 : {{ $bizPhone }}</li>
        @endif
        @if ($bizAddress)
          <li>주소 : {{ $bizAddress }}</li>
        @endif
      </ul>
    </div>

    <div class="policy-section">
      <h3>10. 고지의 의무</h3>
      <p>
        본 개인정보 처리방침은 법령·정책 또는 서비스 변경에 따라 내용이 추가·삭제·수정될 수
        있으며, 변경 시 시행일자를 명시하여 서비스 내 공지를 통해 안내합니다.
      </p>
    </div>
  </div>

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

</body>
</html>
