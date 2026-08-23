<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>결 — 연애 특화 사주</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @if (config('services.toss.client_key'))
    <script src="https://js.tosspayments.com/v1/payment"></script>
  @endif
  <!-- 공유 카드를 실제 HTML/CSS 디자인 그대로 이미지로 캡처하는 데 사용 (public/js/reports.js) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body>

<div class="wrap">

  <div class="topbar">
    @auth
      <div class="user-chip">
        <a class="chip-link" id="topbar-credits" href="{{ route('billing.index') }}">코인 {{ auth()->user()->credits }}개</a>
        <a class="chip-link" href="{{ route('reports.index') }}">내 리포트함</a>
        <span>{{ auth()->user()->name }}님</span>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit">로그아웃</button>
        </form>
      </div>
    @else
      <a class="social-btn kakao" href="{{ route('auth.redirect', 'kakao') }}">카카오로 로그인</a>
      <a class="social-btn naver" href="{{ route('auth.redirect', 'naver') }}">네이버로 로그인</a>
    @endauth
  </div>

  @if (session('billing_success'))
    <div class="placeholder-note" style="margin-top:14px;">{{ session('billing_success') }}</div>
  @endif
  @if (session('billing_error'))
    <div class="card" style="border-color: var(--seal); color: var(--seal-deep); margin-top:14px;">{{ session('billing_error') }}</div>
  @endif

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="41" text-anchor="middle" font-family="Song Myung, serif" font-size="26" fill="var(--seal)">결</text>
    </svg>
    <div class="hero-text">
      <h1>결</h1>
      <p>사주팔자로 읽는 나의 연애 기질과 궁합, 그리고 사주 맥락을 아는 연애 코치</p>
      <p class="sub">천을귀인처럼 좋은 인연이 닿기를. 생년월일시를 입력해 시작하세요.</p>
    </div>
  </div>

  <div class="tabs" role="tablist">
    <button class="tab-btn active" data-tab="single" role="tab">나의 연애 사주</button>
    <button class="tab-btn" data-tab="compat" role="tab">궁합 보기</button>
    <button class="tab-btn" data-tab="guide" role="tab">고민 상담 가이드</button>
    <button class="tab-btn" data-tab="chat" role="tab">연애 코치</button>
  </div>

  <!-- ===================== 1. 나의 연애 사주 ===================== -->
  <section class="panel active" id="panel-single">
    <div class="card">
      <h2>생년월일시 입력</h2>
      <div class="field-row">
        <div>
          <label for="s-name">이름 (선택)</label>
          <input type="text" id="s-name" placeholder="예: 올리">
        </div>
        <div>
          <label for="s-year">태어난 해</label>
          <input type="number" id="s-year" placeholder="1995" min="1900" max="2100">
        </div>
        <div>
          <label for="s-month">월</label>
          <input type="number" id="s-month" placeholder="5" min="1" max="12">
        </div>
        <div>
          <label for="s-day">일</label>
          <input type="number" id="s-day" placeholder="15" min="1" max="31">
        </div>
      </div>
      <div class="field-row">
        <div>
          <label for="s-hour">시</label>
          <input type="number" id="s-hour" placeholder="14" min="0" max="23">
        </div>
        <div>
          <label for="s-minute">분</label>
          <input type="number" id="s-minute" placeholder="30" min="0" max="59">
        </div>
        <div>
          <label for="s-sido">출생 지역 — 시/도</label>
          <select id="s-sido"></select>
        </div>
        <div>
          <label for="s-sigungu">시/군/구</label>
          <select id="s-sigungu"></select>
        </div>
      </div>
      <div class="check-row">
        <input type="checkbox" id="s-unknown">
        <label for="s-unknown" style="margin:0;">태어난 시간을 몰라요 (시주 제외하고 계산)</label>
      </div>
      <button class="btn btn-center" id="s-submit">사주 풀이 보기</button>
      <div class="hint" style="margin-top:10px;">양력 기준으로 입력해 주세요. 음력이면 양력으로 변환 후 입력해 주세요.</div>
    </div>

    <div id="s-result"></div>
  </section>

  <!-- ===================== 2. 궁합 보기 ===================== -->
  <section class="panel" id="panel-compat">
    <div class="card">
      <h2>두 사람의 생년월일시</h2>
      <div class="hint" style="margin-bottom:14px;">궁합은 일간(태어난 날의 천간)과 일지를 중심으로 보기 때문에 태어난 시간이 없어도 계산돼요. 시간을 알면 더 정확해요.</div>
      <div class="compat-people">
        <div class="compat-person compat-person-a">
          <div class="compat-person-label">A</div>
          <label for="c-name-a">이름</label>
          <input type="text" id="c-name-a" placeholder="나">
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-year-a">해</label><input type="number" id="c-year-a" placeholder="1995"></div>
            <div><label for="c-month-a">월</label><input type="number" id="c-month-a" placeholder="5"></div>
            <div><label for="c-day-a">일</label><input type="number" id="c-day-a" placeholder="15"></div>
          </div>
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-hour-a">시</label><input type="number" id="c-hour-a" placeholder="14" min="0" max="23"></div>
            <div><label for="c-minute-a">분</label><input type="number" id="c-minute-a" placeholder="30" min="0" max="59"></div>
          </div>
          <div class="check-row">
            <input type="checkbox" id="c-unknown-a">
            <label for="c-unknown-a" style="margin:0;">태어난 시간을 몰라요</label>
          </div>
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-sido-a">출생 지역 — 시/도</label><select id="c-sido-a"></select></div>
            <div><label for="c-sigungu-a">시/군/구</label><select id="c-sigungu-a"></select></div>
          </div>
        </div>
        <div class="compat-person compat-person-b">
          <div class="compat-person-label">B</div>
          <label for="c-name-b">이름</label>
          <input type="text" id="c-name-b" placeholder="상대">
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-year-b">해</label><input type="number" id="c-year-b" placeholder="1996"></div>
            <div><label for="c-month-b">월</label><input type="number" id="c-month-b" placeholder="9"></div>
            <div><label for="c-day-b">일</label><input type="number" id="c-day-b" placeholder="2"></div>
          </div>
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-hour-b">시</label><input type="number" id="c-hour-b" placeholder="9" min="0" max="23"></div>
            <div><label for="c-minute-b">분</label><input type="number" id="c-minute-b" placeholder="0" min="0" max="59"></div>
          </div>
          <div class="check-row">
            <input type="checkbox" id="c-unknown-b">
            <label for="c-unknown-b" style="margin:0;">태어난 시간을 몰라요</label>
          </div>
          <div class="field-row" style="margin-top:8px;">
            <div><label for="c-sido-b">출생 지역 — 시/도</label><select id="c-sido-b"></select></div>
            <div><label for="c-sigungu-b">시/군/구</label><select id="c-sigungu-b"></select></div>
          </div>
        </div>
      </div>
      <button class="btn btn-center" id="c-submit">궁합 보기</button>
    </div>

    <div id="c-result"></div>
  </section>

  <!-- ===================== 3. 고민 상담 가이드 (룰 기반, 즉시 응답) ===================== -->
  <section class="panel" id="panel-guide">
    <div class="card">
      <h2>지금 어떤 고민이 있나요?</h2>
      <div class="hint" style="margin-bottom:14px;">먼저 '나의 연애 사주' 탭에서 풀이를 한 번 보면, 아래 조언이 내 사주 기질에 맞춰 나와요. 아직 안 봤다면 일반적인 조언으로 보여드릴게요.</div>
      <div class="concern-grid" id="concern-grid"></div>
      <div id="guide-result"></div>

      <div class="placeholder-note">
        <strong>참고</strong> — 이 조언은 정해진 규칙과 사주 데이터를 조합해 그 자리에서 생성하는 가이드예요. 자유롭게 대화하며 상담받고 싶으면 <strong>연애 코치</strong> 탭에서 AI와 직접 대화할 수 있어요.
      </div>
    </div>
  </section>

  <!-- ===================== 4. 연애 코치 (실시간 AI 호출, 로그인 필요) ===================== -->
  <section class="panel" id="panel-chat">
    <div class="card">
      <h2>연애 코치와 이야기하기</h2>

      @guest
        <div class="login-gate">
          <p>
            수많은 연애 상담 사례를 학습한 AI 코치예요. 실시간으로 답변을 생성하고 대화 기록을 저장하기 때문에 로그인이 필요해요.<br>
            사주 계산·궁합·상담가이드는 로그인 없이 계속 무료로 쓰실 수 있어요.
          </p>
          <a class="social-btn kakao" href="{{ route('auth.redirect', 'kakao') }}">카카오로 시작하기</a>
          <a class="social-btn naver" href="{{ route('auth.redirect', 'naver') }}">네이버로 시작하기</a>
        </div>
      @else
        <div id="chat-error"></div>

        <div id="chat-history"></div>

        <div id="chat-setup">
          <p class="hint" style="margin-bottom:14px;">
            '나의 연애 사주' 탭에서 먼저 사주를 계산해 두면, 코치가 그 정보를 참고해서 훨씬 더 맞춤화된 조언을 줘요.
            (계산해 두지 않아도 일반 상담으로 바로 시작할 수 있어요.)
          </p>
          <button class="btn btn-center" id="chat-start">새 상담 시작하기</button>
          <div class="hint" style="margin-top:10px; margin-bottom:0;">
            남은 메시지 {{ auth()->user()->credits }}개 · <a href="{{ route('billing.index') }}">코인 충전하기</a>
          </div>
        </div>

        <div id="chat-room">
          <button class="chat-new-btn" id="chat-back">&larr; 상담 목록으로</button>
          <div id="chat-log"></div>
          <div id="chat-typing">코치가 답변을 쓰고 있어요…</div>
          <div class="chat-input-row">
            <input type="text" id="chat-input" placeholder="편하게 이야기해 주세요…" autocomplete="off">
            <button class="btn" id="chat-send">보내기</button>
          </div>
          <div class="hint" id="chat-credits" style="margin-top:8px; margin-bottom:0;"></div>
        </div>
      @endguest
    </div>
  </section>

  <footer>
    사주는 태양의 움직임(절기)을 기준으로 한 전통 역법 계산에 성격 해석을 더한 것으로, 통계적·문화적 참고용 콘텐츠입니다. 연애의 실제 결과를 보장하지 않으며, 중요한 결정은 실제 관계와 대화를 통해 내리시길 권해요. 절기 경계(입춘 등) 부근 출생은 계산이 실제 만세력과 몇 분 이내로 달라질 수 있어요. 연애 코치 탭의 답변은 AI가 생성한 것으로, 전문 심리상담이나 법률·의료 조언을 대체하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

<script>
  window.YeonbunAuth = {
    loggedIn: @json(auth()->check()),
    name: @json(auth()->user()->name ?? null)
  };
  window.YeonbunBilling = {
    tossConfigured: @json((bool) config('services.toss.client_key')),
    tossClientKey: @json(config('services.toss.client_key')),
    reportsCheckoutUrl: @json(route('reports.checkout')),
    reportsSuccessUrl: @json(route('reports.success')),
    reportsFailUrl: @json(route('reports.fail')),
    kakaoLoginUrl: @json(route('auth.redirect', 'kakao')),
    naverLoginUrl: @json(route('auth.redirect', 'naver'))
  };
</script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/reports.js') }}"></script>
<script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
