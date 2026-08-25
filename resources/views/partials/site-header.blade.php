{{--
  전역 상단 헤더 (2026-08-24 신설, 헤더/하단탭 도입 작업).

  (2026-08-25 브랜드명 변경: 결 → 연록. "인연록(因緣錄)"에서 '인'을 뺀 이름 — 인연의
  기록이라는 뜻은 그대로 담고 있다. 팔레트/도장 모티프는 그대로 유지하고 이름만 교체.)

  (2026-08-25 추가, 로고 이미지 적용) 텍스트였던 워드마크를 사용자가 로고 제작 AI로 만든
  실제 로고 이미지(브러시 도장 아이콘 + "연록" 워드마크)로 교체했다. <picture>로 라이트/
  다크 모드 두 버전(public/images/logo/yeonrok-logo-{light,dark}.png)을 분기 — 원본
  이미지에서 검정 잉크와 빨간 점을 이 사이트의 실제 --ink/--seal 값으로 다시 칠하고
  배경은 투명 처리해서 뽑아낸 파일이다(AI가 준 빨간 점은 사이트 --seal 색(자주/모브 톤)과
  안 맞아서 브랜드 컬러에 맞게 보정함). data-theme 수동 토글은 아직 JS로 연결돼 있지
  않아서(CSS만 존재) prefers-color-scheme만으로 충분히 커버됨.

  좌측엔 "연록" 로고 이미지만, 우측엔 로그인 상태에 따라 코인 잔액 칩(로그인 시) 또는 "로그인"
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
  <a class="site-header-logo" href="{{ route('home') }}">
    <picture>
      <source srcset="{{ asset('images/logo/yeonrok-logo-dark.png') }}" media="(prefers-color-scheme: dark)">
      <img src="{{ asset('images/logo/yeonrok-logo-light.png') }}" alt="연록" class="site-header-logo-img">
    </picture>
  </a>
  @auth
    <a class="site-header-coin" id="topbar-credits" href="{{ route('billing.index') }}">코인 {{ auth()->user()->credits }}개</a>
  @else
    <a class="site-header-login" href="{{ route('my.index') }}">로그인</a>
  @endauth
</div>
