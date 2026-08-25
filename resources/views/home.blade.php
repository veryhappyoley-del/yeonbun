{{--
  진짜 홈 랜딩페이지 (2026-08-25 신설, 로드맵 3번).

  배경: 지금까지 "/"(home 라우트)는 계산기 화면(현재의 /calculator, saju.blade.php)을
  겸하고 있었다. 헤더/하단탭 구조가 자리잡으면서 하단 탭바 "홈"이 실제로 가리킬 전용
  마케팅/소개 페이지가 필요해졌고, 사용자가 경쟁사 myeongsado.com 구성을 참고해 달라고
  요청했다. myeongsado의 구조(히어로 큰 슬로건+CTA → 차별점 소개 → 상품/전문가 카드 →
  푸터)는 그대로 가져오되, 색/타이포/톤은 절대 따라하지 않고 결의 종이/먹색/인주색
  palette(Song Myung 세리프 제목, Gowun Dodum 본문)로 완전히 새로 입혔다.

  구성:
  1. 히어로 — 큰 슬로건 + 단일 CTA("/sagu"로 유도, 종목은 거기서 고르게 함)
  2. "결의 차별점" — 실제로 만든 기능만 정직하게 4가지(home-feature-grid)
  3. "무엇을 볼 수 있나요" — 종목 미리보기 카드 3개(sagu-card 재사용) + "/sagu" 전체보기 링크
  4. 비즈니스 푸터 + 하단 탭바

  가짜 후기/과장된 통계는 넣지 않는다(정직한 마케팅 원칙 — 이 세션 내내 지켜온 기준).

  (2026-08-25 6차 수정) 원래 맨 위에 전역 헤더(site-header)를 넣었었는데, 사용자가
  "디자인적으로 어색하고 내용이 없다"고 지적했다 — 히어로에 이미 도장 스탬프 애니메이션으로
  "결" 브랜드가 강하게 등장하는데 그 바로 위에 작은 "결" 워드마크가 또 있으니 중복돼
  보였고, 로그아웃 상태에서는 "결 ... 로그인"만 덩그러니 있어서 휑해 보였다. 홈페이지에서는
  헤더가 실질적으로 하는 일(코인 잔액 확인, 로고로 홈 이동)이 둘 다 의미가 없다 — 이미
  홈이고, 코인은 쓸 일이 없는 페이지라서. 그래서 홈페이지에서만 헤더를 뺐다(계산기/사주/
  마이/사전/리포트 등 다른 페이지는 코인 잔액을 실제로 봐야 하니 그대로 유지). 대신
  하단 탭바가 로그인/마이 진입을 계속 담당한다. --}}
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="사주팔자로 읽는 나의 연애 기질과 궁합, 그리고 내 사주 맥락을 아는 AI 연애 코치까지. 결에서 무료로 시작해보세요.">
  <title>결 — 사주로 읽는 나의 연애</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap">

  <div class="home-hero">
    {{-- (2026-08-25 4차 수정) 이전엔 도장 스탬프만 애니메이션하고 제목/부제/CTA는 "핵심
         콘텐츠는 애니메이션에 가려 늦게 보이면 안 된다"는 원칙에 따라 항상 즉시 보이게
         했었는데, 사용자가 스크린샷에 빨간 박스로 표시한 영역(도장+제목+부제+버튼+안내
         문구) 전체에 애니메이션을 원한다고 명시적으로 요청해서 이번엔 그 원칙을 의도적으로
         완화했다. 대신 두 아이디어를 조합해서(사용자가 여러 레퍼런스 중 직접 골랐다):

         ① 스포트라이트(조명이 켜지는 느낌) — 히어로 전체(.home-hero)가 아주 잠깐
            어둡고 흐릿한 상태에서 밝고 또렷하게 살아나는 연출. Stripe/Vercel류 사이트가
            콘텐츠 밝기를 이렇게 스윽 올리는 방식을 자주 쓴다.
         ② 스태거드 리빌(순서대로 떠오르기) — 도장이 착지한 직후 제목→부제→버튼→안내
            문구가 80ms 간격으로 아래에서 살짝 떠오르며 나타남. Linear.app/Stripe.com이
            로드될 때 요소들이 "톡톡톡" 순서대로 떨어지듯 들어오는 것과 같은 리듬.

         전부 합쳐도 0.7초 안에 끝나는 짧은 연출이라 체감 로딩 지연은 없다. CTA 버튼은
         `.home-hero-cta-wrap`으로 한 번 감싸서 그 wrapper에 슬라이드업 애니메이션을
         걸었다 — `<a class="btn">` 자체에 걸면 `.btn:active { transform: scale(0.98) }`
         같은 클릭 피드백이, animation-fill-mode:forwards가 transform을 계속 붙잡고 있는
         바람에 눌러도 반응 안 하는 버그가 생기기 때문이다(실제 버튼 요소의 transform은
         항상 자유롭게 남겨둬야 함). --}}
    <div class="home-hero-stamp" aria-hidden="true">
      <span class="home-hero-stamp-ring"></span>
      <span class="home-hero-stamp-ring home-hero-stamp-ring--2"></span>
      <svg class="home-hero-center" viewBox="0 0 64 64" aria-hidden="true">
        <rect x="4" y="4" width="56" height="56" rx="8" fill="var(--paper)" stroke="var(--seal)" stroke-width="3"></rect>
        <text x="32" y="41" text-anchor="middle" font-family="Song Myung, serif" font-size="26" fill="var(--seal)">결</text>
      </svg>
    </div>
    <h1>사주로 읽는,<br>나의 연애</h1>
    <p>생년월일시 하나로 나의 연애 기질, 두 사람의 궁합, 그리고 내 사주 맥락을 아는 AI 코치까지.</p>
    <div class="home-hero-cta-wrap">
      <a class="btn home-hero-cta" href="{{ route('sagu.index') }}">무료로 궁금한 것 고르기</a>
    </div>
    <div class="hint home-hero-hint" style="margin-top:10px;">가입 없이 바로 시작할 수 있어요.</div>
  </div>

  {{-- (2026-08-25 5차 수정) 처음엔 report-trust-grid(결제 CTA 아래 붙는 작은 신뢰 배지용
       컴포넌트)를 그대로 재활용했는데, 그건 원래 "버튼 밑에 작게 딸린 보조 정보" 용도로
       만들어진 거라 아이콘/글자가 다 작고 여백도 빡빡해서, 홈페이지의 정식 섹션으로 쓰기엔
       가독성이 떨어지고 전문적인 느낌도 약하다는 피드백을 받았다. 그래서 홈 전용
       `.home-feature-*` 컴포넌트로 분리해서 새로 만들었다 — report-trust-grid는 원래
       용도(결제 CTA 아래)에 그대로 남겨두고 건드리지 않았다. 아이콘을 이모지 단독이
       아니라 색이 있는 뱃지 안에 넣고(Stripe/Linear류 SaaS 사이트의 "기능 카드" 아이콘
       처리 방식), 카드 padding과 글자 크기를 키우고, 상단에 킥커+한 줄 소개문을 추가해서
       섹션 전체가 더 격식 있어 보이게 했다.

       (2026-08-25 6차 수정) 제목을 "왜 결인가요?"(질문형)에서 "이래서 결이 다릅니다"
       (선언형/강점 서술형)로 바꿨다 — 사용자 피드백: 질문 형식은 자신 없어 보이고, 강점을
       당당하게 말하는 톤이 더 낫다는 것. 킥커도 "WHY 결"(질문 반복)에서 "결의 차별점"으로
       바꿨다. 브랜드 이름 자체가 나중에 바뀔 수 있어서(연인록/미묘 등 후보 논의 중이지만
       사용자가 이름 결정은 보류하기로 함) 문구에 "결"을 최소한으로만 남기고, 이름이
       바뀌어도 어색하지 않게 "이래서 다릅니다" 쪽에 무게를 실었다. --}}
  <div class="card">
    <div class="home-feature-kicker">결의 차별점</div>
    <h2>이래서 결이 다릅니다</h2>
    <p class="home-feature-intro">다른 사주 서비스에는 없는, 결만의 강점이에요.</p>
    <div class="home-feature-grid">
      <div class="home-feature-item">
        <div class="home-feature-icon">💘</div>
        <div class="home-feature-head">연애에만 집중</div>
        <div class="home-feature-desc">인생 전반이 아니라 연애·궁합만 깊이 파고들어요</div>
      </div>
      <div class="home-feature-item">
        <div class="home-feature-icon">🤖</div>
        <div class="home-feature-head">내 사주를 아는 코치</div>
        <div class="home-feature-desc">계산해 둔 내 사주 맥락을 기억하는 AI와 실시간 대화</div>
      </div>
      <div class="home-feature-item">
        <div class="home-feature-icon">📖</div>
        <div class="home-feature-head">쉬운 해석</div>
        <div class="home-feature-desc">어려운 명리학 용어 대신 일상 언어로 풀어써요</div>
      </div>
      <div class="home-feature-item">
        <div class="home-feature-icon">♾️</div>
        <div class="home-feature-head">평생 소장</div>
        <div class="home-feature-desc">한 번 본 결과와 리포트는 리포트함에 계속 남아요</div>
      </div>
    </div>
  </div>

  <div class="card">
    <h2>무엇을 볼 수 있나요</h2>
    <div class="sagu-card-list">
      <a class="sagu-card" href="{{ route('calculator.index', ['tab' => 'single']) }}">
        <span class="sagu-card-badge" aria-hidden="true">사</span>
        <div class="sagu-card-body">
          <div class="sagu-card-title">나의 연애 사주</div>
          <div class="sagu-card-desc">내 사주로 보는 연애 기질과 성향, 그리고 앞으로의 연애운.</div>
        </div>
        <span class="sagu-card-price">무료로 시작</span>
      </a>
      <a class="sagu-card" href="{{ route('calculator.index', ['tab' => 'compat']) }}">
        <span class="sagu-card-badge" aria-hidden="true">궁</span>
        <div class="sagu-card-body">
          <div class="sagu-card-title">궁합 보기</div>
          <div class="sagu-card-desc">두 사람의 사주로 보는 궁합 점수와 관계의 흐름.</div>
        </div>
        <span class="sagu-card-price">무료로 시작</span>
      </a>
      <a class="sagu-card" href="{{ route('calculator.index', ['tab' => 'chat']) }}">
        <span class="sagu-card-badge" aria-hidden="true">코</span>
        <div class="sagu-card-body">
          <div class="sagu-card-title">연애 코치</div>
          <div class="sagu-card-desc">내 사주 맥락을 아는 AI 코치와 실시간으로 연애 상담.</div>
        </div>
        <span class="sagu-card-price">코인으로 상담</span>
      </a>
    </div>
    <div class="hint" style="margin-top:14px;">
      <a href="{{ route('sagu.index') }}">더 많은 카테고리 보기 &rarr;</a>
    </div>
  </div>

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

</body>
</html>
