{{--
  전역 상단 헤더 (2026-08-24 신설, 헤더/하단탭 도입 작업).

  좌측엔 "결" 워드마크만, 우측엔 로그인 상태에 따라 코인 잔액 칩(로그인 시) 또는 "로그인"
  링크(비로그인 시)만 가볍게 보여준다. 계정 정보/로그아웃/리포트함처럼 무거운 건 전부
  마이페이지(site-bottom-nav.blade.php의 "마이"/"로그인" 탭 목적지, my.blade.php)로 몰아서,
  헤더는 "지금 코인 얼마 있지?" 정도만 한눈에 보이게 가볍게 유지한다.

  기존 saju.blade.php에만 있던 .topbar(로그인 폼+코인+리포트함+로그아웃을 한 줄에 다 넣은
  버전)를 이 전역 헤더 + 마이페이지 조합으로 대체했다. 소비자용 화면(홈/사주/사전/마이/
  리포트함/리포트 상세/코인 충전/결제 완료)에 전부 이 파셜을 넣는다(관리자 대시보드 제외).

  id="topbar-credits"는 그대로 유지해야 한다 — public/js/chat.js의 updateCreditsDisplay()가
  채팅 메시지를 보낼 때마다 이 id로 코인 잔액을 실시간 갱신한다(요소가 없는 페이지에서는
  null 체크로 조용히 건너뛰므로 안전하다).
--}}
<div class="site-header">
  <a class="site-header-logo" href="{{ route('home') }}">결</a>
  @auth
    <a class="site-header-coin" id="topbar-credits" href="{{ route('billing.index') }}">코인 {{ auth()->user()->credits }}개</a>
  @else
    <a class="site-header-login" href="{{ route('my.index') }}">로그인</a>
  @endauth
</div>
