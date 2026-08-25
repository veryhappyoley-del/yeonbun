@php
  // (2026-08-25 추가) 이 페이지는 이제 /calculator에서만 서비스된다("/"는 home.blade.php로
  // 분리됨). /sagu의 카드를 눌러 ?tab=single|compat|chat으로 들어온 경우, /sagu에서 이미
  // "결" 로고+설명+종목 카드를 다 보고 왔기 때문에 여기서 히어로(결 로고+설명)와 상단 탭
  // 스트립을 또 보여주면 같은 내용이 중복돼 어색하다(사용자 피드백: 빨간 박스로 표시된
  // 영역 없애달라는 요청). 그래서 유효한 tab 값으로 들어온 경우엔 그 영역을 시각적으로
  // 숨긴다 — DOM에서 완전히 지우지는 않는데, public/js/app.js의 activateTabFromQuery()가
  // 여전히 .tab-btn 버튼을 찾아 .click()을 호출해서 탭 전환 로직(어떤 .panel이 보일지)을
  // 그대로 재사용하기 때문이다(display:none이어도 JS로 호출하는 .click()은 정상 동작함).
  // 값이 없거나 모르는 값이면(직접 "/calculator"로 들어온 경우 등) 평소대로 다 보여준다.
  $incomingTab = request()->query('tab');
  $hideCalcChrome = in_array($incomingTab, ['single', 'compat', 'chat'], true);

  // 결제 CTA(public/js/reports.js buildCTA)의 "목차 미리보기"에 쓸 정적 데이터.
  // AI 콘텐츠는 전혀 포함하지 않고, 이미 코드로 정의된 챕터 제목/티저만 그대로 노출한다.
  // (2026-08-24 수정) previewChapters()를 써서 ReportType::$previewChapterKeys가 지정된
  // 타입(예: 궁합분석)은 전체가 아니라 고른 일부만 노출하고, totalChapters로 실제 전체
  // 개수를 함께 내려줘서 프론트가 "20개 챕터 중 12개 미리보기" 같은 문구를 만들 수 있게 함.
  $reportTypePreviews = collect(\App\ReportTypes\ReportTypeRegistry::all())->map(function ($type) {
      return [
          'key' => $type->key,
          'label' => $type->label,
          'totalChapters' => $type->chapterCount(),
          'chapters' => collect($type->previewChapters())->map(function ($chapter) {
              return ['title' => $chapter->title, 'teaser' => $chapter->teaser];
          })->values(),
      ];
  })->values();
@endphp
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>연록 — 연애 특화 사주</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @if (config('services.toss.client_key'))
    <script src="https://js.tosspayments.com/v1/payment"></script>
  @endif
  <!-- 공유 카드를 실제 HTML/CSS 디자인 그대로 이미지로 캡처하는 데 사용 (public/js/reports.js) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap">

  {{-- (2026-08-24 수정) 로그인 폼+코인+리포트함+로그아웃을 한 줄에 다 넣던 예전 .topbar를
       전역 헤더(코인 칩/로그인 링크만)로 대체. 계정 관리는 마이페이지(하단 탭바 "마이")로
       옮겼다. --}}
  @include('partials.site-header')

  @if (session('billing_success'))
    <div class="placeholder-note" style="margin-top:14px;">{{ session('billing_success') }}</div>
  @endif
  @if (session('billing_error'))
    <div class="card" style="border-color: var(--seal); color: var(--seal-deep); margin-top:14px;">{{ session('billing_error') }}</div>
  @endif

  <div class="hero @if ($hideCalcChrome) is-hidden @endif">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>연록</h1>
      <p>사주팔자로 읽는 나의 연애 기질과 궁합, 그리고 사주 맥락을 아는 연애 코치</p>
      <p class="sub">천을귀인처럼 좋은 인연이 닿기를. 생년월일시를 입력해 시작하세요.</p>
    </div>
  </div>

  <div class="tabs @if ($hideCalcChrome) is-hidden @endif" role="tablist">
    <button class="tab-btn active" data-tab="single" role="tab">나의 연애 사주</button>
    <button class="tab-btn" data-tab="compat" role="tab">궁합 보기</button>
    <button class="tab-btn" data-tab="chat" role="tab">연애 코치</button>
  </div>
  <!-- "고민 상담 가이드" 탭은 상단 메뉴에서 뺐습니다(완성도가 낮다는 판단, 2026-08-24).
       #panel-guide 섹션 자체는 아래에 그대로 남아있어요 — public/js/app.js의 bindEvents()가
       #concern-grid에 콘텐츠를 채워 넣는데, 이 요소가 DOM에서 사라지면 그 스크립트가 에러를
       던지면서 뒤에 이어지는 다른 초기화(fillCitySelects/이벤트 바인딩 등)까지 멈출 수 있어서
       안전하게 그대로 둡니다. 탭 버튼이 없으니 사용자는 이 패널에 절대 진입할 수 없어요
       (.panel은 기본 display:none, .active가 있어야만 보임 — app.css 참고). -->


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

      <!-- (2026-08-24 추가) 관계 단계/관심사 선택 — 프리미엄 궁합분석 리포트가 이 값에 맞춰
           톤과 강조 챕터를 조정한다(public/js/app.js가 단일 선택 토글, reports.js
           buildTwoPersonInput이 relationshipStage/primaryConcern/concernDetail로 전송,
           App\ReportTypes\Definitions\CompatibilityReportType가 프롬프트에 반영).
           둘 다 선택 안 해도 궁합 보기는 그대로 동작한다(선택 사항). -->
      <div class="compat-context">
        <div class="compat-context-label">현재 관계</div>
        <div class="compat-context-hint">두 사람이 지금 어떤 사이인지에 따라 리포트가 읽는 톤이 달라져요.</div>
        <div class="compat-stage-row" id="c-stage-row">
          <button type="button" class="compat-stage-chip" data-stage="seom">썸</button>
          <button type="button" class="compat-stage-chip" data-stage="couple">커플</button>
          <button type="button" class="compat-stage-chip" data-stage="married">부부</button>
          <button type="button" class="compat-stage-chip" data-stage="breakup">헤어짐</button>
        </div>

        <div class="compat-context-label">지금 가장 궁금한 것은?</div>
        <div class="compat-context-hint">선택하신 내용에 맞춰 프리미엄 리포트의 분석 방향이 달라져요.</div>
        <div class="compat-concern-grid" id="c-concern-grid">
          <button type="button" class="compat-concern-card" data-concern="continuity">
            <span class="compat-concern-icon">♾️</span>
            <span class="compat-concern-title">지속 가능성</span>
            <span class="compat-concern-desc">잘 맞는지, 이대로 이어질 수 있는지</span>
          </button>
          <button type="button" class="compat-concern-card" data-concern="growth">
            <span class="compat-concern-icon">📈</span>
            <span class="compat-concern-title">관계 발전</span>
            <span class="compat-concern-desc">연애·결혼 등 다음 단계로 갈 수 있을지</span>
          </button>
          <button type="button" class="compat-concern-card" data-concern="flow">
            <span class="compat-concern-icon">📅</span>
            <span class="compat-concern-title">앞으로의 흐름</span>
            <span class="compat-concern-desc">가까워질 시기·멀어질 시기가 궁금할 때</span>
          </button>
          <button type="button" class="compat-concern-card" data-concern="friction">
            <span class="compat-concern-icon">🛡️</span>
            <span class="compat-concern-title">충돌 완화</span>
            <span class="compat-concern-desc">싸움·오해·마찰이 반복되는 이유</span>
          </button>
        </div>

        <label for="c-concern-detail" class="compat-context-label">가장 궁금한 1가지는 무엇인가요? <span class="compat-context-optional">(선택, 최대 40자)</span></label>
        <input type="text" id="c-concern-detail" maxlength="40" placeholder="예) 이 사람과 결혼까지 갈 수 있을까요">
      </div>

      <button class="btn btn-center" id="c-submit" style="margin-top:18px;">궁합 보기</button>
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
          {{-- (2026-08-25 추가, 로드맵 1·2번) 로그인하고 돌아왔을 때 다시 연애 코치 탭으로
               떨어지도록 redirect 파라미터를 붙인다. 이 게이트는 이 페이지(계산기, 항상
               /calculator)의 연애 코치 탭 안에서만 보이므로 경로를 그대로 고정해도 된다. --}}
          <a class="social-btn kakao" href="{{ route('auth.redirect', ['provider' => 'kakao', 'redirect' => '/calculator?tab=chat']) }}">카카오로 시작하기</a>
          <a class="social-btn naver" href="{{ route('auth.redirect', ['provider' => 'naver', 'redirect' => '/calculator?tab=chat']) }}">네이버로 시작하기</a>
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
    사주는 태양의 움직임(절기)을 기준으로 한 전통 역법 계산에 성격 해석을 더한 것으로, 통계적·문화적 참고용 콘텐츠입니다. 연애의 실제 결과를 보장하지 않으며, 중요한 결정은 실제 관계와 대화를 통해 내리시길 권해요. 절기 경계(입춘 등) 부근 출생은 계산이 실제 만세력과 몇 분 이내로 달라질 수 있어요. 연애 코치 탭과 프리미엄 리포트(연애운분석·궁합분석)의 답변은 고전 명리학 이론을 폭넓게 학습한 AI가 생성한 것으로, 전문 심리상담이나 법률·의료 조언을 대체하지 않아요.
    @include('partials.business-footer')
  </footer>
</div>

@include('partials.site-bottom-nav')

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
    naverLoginUrl: @json(route('auth.redirect', 'naver')),
    // (2026-08-24 추가) 결제 전 "무료 미리보기" 챕터 생성/폴링 엔드포인트. 로그인 없이도
    // 쓸 수 있음(routes/web.php에서 auth 미들웨어 밖에 등록) — app.js의 궁합 결과 화면이
    // 씀. 자세한 설계는 App\Http\Controllers\ChapterPreviewController 주석 참고.
    chapterPreviewsUrl: @json(route('chapter-previews.store'))
  };
  // 공유 카드(연애 캐릭터 카드 등)에 사이트 유입 유도 문구/링크를 넣을 때 씀 — 정적 JS 파일은
  // Blade의 route()/url() 헬퍼를 직접 못 쓰기 때문에 여기서 서버가 렌더링해서 넘겨줌.
  window.YeonbunSite = {
    url: @json(url('/')),
    host: @json(request()->getHost())
  };
  // 결제 전 "목차 미리보기"(public/js/reports.js)가 쓰는 정적 데이터 — AI 호출 없이
  // App\ReportTypes\ReportTypeRegistry에 이미 정의된 챕터 제목/티저만 그대로 노출한다.
  // 콘텐츠(본문)는 절대 여기 포함되지 않는다 — 결제 전 사용자는 "무엇을 받는지"만 알 수 있다.
  window.YeonbunReportPreview = @json($reportTypePreviews);
</script>
<script src="{{ asset('js/love-character.js') }}"></script>
<script src="{{ asset('js/compat-character.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/reports.js') }}"></script>
<script src="{{ asset('js/chat.js') }}"></script>
</body>
</html>
