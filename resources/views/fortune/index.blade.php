{{--
  "오늘의 운세" 구독 페이지 (2026-08-31 신설). saju.blade.php의 계산기(public/js/app.js,
  REGIONS 시/군/구 캐스케이드 포함)를 로드하지 않는 독립 페이지로 만들었다 — 이 페이지는
  정밀한 절기 계산이 필요 없고(일주만 쓰므로) 시/군/구 경도 보정 없이 서울 기본값으로
  충분하다고 판단해서, 무거운 계산기 JS 전체를 끌어오는 대신 이 파일 안에 아주 작은
  자체 스크립트(성별 칩 토글 + 시간 모름 체크박스 + 토스 카드 등록)만 넣었다.
--}}
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>오늘의 운세 구독 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @if ($tossConfigured)
    <script src="https://js.tosspayments.com/v1/payment"></script>
  @endif
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
      <h1>오늘의 운세</h1>
      <p>매일 새벽, 그날의 사주 흐름을 이메일로 보내드려요.</p>
    </div>
  </div>

  @if (session('fortune_success'))
    <div class="card" style="border-color: var(--seal);">{{ session('fortune_success') }}</div>
  @endif
  @if (session('fortune_error'))
    <div class="card" style="border-color: var(--seal); color: var(--seal-deep);">{{ session('fortune_error') }}</div>
  @endif

  @if ($subscription && $subscription->isActive())
    {{-- 구독 중 --}}
    <div class="card">
      <h2>구독 중이에요</h2>
      <div class="hint" style="margin-bottom:12px;">
        매달 {{ number_format($price) }}원이 자동으로 결제돼요. 다음 결제일:
        {{ $subscription->next_billing_date?->format('Y년 n월 j일') ?? '-' }}
      </div>
      @if ($latestFortune)
        <a class="btn btn-center" href="{{ route('fortune.today') }}">오늘의 운세 보기</a>
      @else
        <div class="placeholder-note">아직 첫 운세가 준비 중이에요 — 내일 새벽에 첫 이메일이 도착해요.</div>
      @endif
    </div>

    <div class="card">
      <h2>구독 관리</h2>
      <form method="POST" action="{{ route('fortune.cancel') }}" onsubmit="return confirm('정말 해지하시겠어요? 다음 결제부터 청구되지 않아요.');">
        @csrf
        <button type="submit" class="btn outline">구독 해지</button>
      </form>
    </div>
  @elseif ($profile)
    {{-- 프로필은 있지만 구독은 없거나(pending/canceled) 결제 실패(past_due) 상태 --}}
    @if ($subscription && $subscription->status === 'past_due')
      <div class="card" style="border-color: var(--seal); color: var(--seal-deep);">
        결제 카드에 문제가 있어 구독이 일시 중지됐어요. 카드를 다시 등록해 주세요.
      </div>
    @endif

    <div class="card" style="text-align:center;">
      <h2>구독 시작하기</h2>
      <div class="hint" style="margin-bottom:16px;">
        {{ $profile->name ?? '회원' }}님의 저장된 생년월일시로 매일 오늘의 운세를 만들어드려요.
      </div>
      <div class="plan-price" style="margin-bottom:4px;">{{ number_format($price) }}원<span style="font-size:0.5em;">/월</span></div>
      @if ($tossConfigured)
        <button type="button" id="fortune-subscribe-btn" class="btn btn-center">카드 등록하고 구독 시작</button>
      @else
        <div class="placeholder-note">결제 기능이 아직 설정되지 않았어요.</div>
      @endif
      <div class="hint" style="margin-top:12px;">언제든 해지할 수 있어요.</div>
    </div>

    <div class="card">
      <h2>내 정보 수정</h2>
      <div class="hint" style="margin-bottom:10px;">
        저장된 정보: {{ $profile->birth_date->format('Y년 n월 j일') }}
        @if (! $profile->birth_time_unknown && $profile->birth_hour !== null)
          {{ $profile->birth_hour }}시 {{ $profile->birth_minute }}분
        @else
          (시간 모름)
        @endif
        · {{ $profile->gender === 'male' ? '남자' : '여자' }}
      </div>
      <details>
        <summary class="chip-link" style="cursor:pointer;">정보 다시 입력하기</summary>
        @include('fortune.partials.profile-form', ['profile' => $profile])
      </details>
    </div>
  @else
    {{-- 아직 프로필이 없음 — 먼저 생년월일시부터 --}}
    <div class="card">
      <h2>생년월일시 입력</h2>
      <div class="hint" style="margin-bottom:14px;">한 번만 입력해두면 매일 자동으로 오늘의 운세를 만들어드려요.</div>
      @include('fortune.partials.profile-form', ['profile' => null])
    </div>
  @endif

  <div class="placeholder-note">
    사주는 통계적·문화적 참고용 콘텐츠이며 실제 결과를 보장하지 않아요. 경도 보정 없이
    서울 기준으로 계산돼서, 계산기(연애의 나침반) 결과와 아주 드물게(자정 부근 출생)
    시간대 경계가 다르게 나올 수 있어요.
  </div>

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

<script>
(function () {
  // 성별 칩 — public/js/app.js의 wireSingleSelect와 같은 동작이지만, 이 페이지는
  // 그 무거운 파일을 로드하지 않으므로 여기에 아주 작게 다시 둔다.
  var genderRow = document.getElementById('fortune-gender-row');
  if (genderRow) {
    genderRow.querySelectorAll('.compat-gender-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        genderRow.querySelectorAll('.compat-gender-chip').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        document.getElementById('fortune-gender-input').value = chip.dataset.gender;
      });
    });
  }

  var unknownCheckbox = document.getElementById('fortune-unknown');
  if (unknownCheckbox) {
    var timeFields = document.getElementById('fortune-time-fields');
    var toggleTimeFields = function () {
      timeFields.style.opacity = unknownCheckbox.checked ? '0.4' : '1';
      timeFields.querySelectorAll('input').forEach(function (i) { i.disabled = unknownCheckbox.checked; });
    };
    unknownCheckbox.addEventListener('change', toggleTimeFields);
    toggleTimeFields();
  }

  @if ($tossConfigured)
  var subscribeBtn = document.getElementById('fortune-subscribe-btn');
  if (subscribeBtn) {
    var tossPayments = TossPayments(@json($tossClientKey));

    function csrfToken() {
      var meta = document.querySelector('meta[name="csrf-token"]');
      return meta ? meta.getAttribute('content') : '';
    }

    subscribeBtn.addEventListener('click', function () {
      subscribeBtn.disabled = true;

      fetch('{{ route('fortune.checkout') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        body: '{}'
      })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.body.error || '구독을 시작하지 못했어요.');

          return tossPayments.requestBillingAuth('카드', {
            customerKey: r.body.customer_key,
            successUrl: '{{ route('fortune.confirm') }}',
            failUrl: '{{ route('fortune.index') }}'
          });
        })
        .catch(function (error) {
          if (error && error.code === 'USER_CANCEL') return;
          alert((error && error.message) || '카드 등록 중 문제가 발생했어요.');
        })
        .finally(function () { subscribeBtn.disabled = false; });
    });
  }
  @endif
})();
</script>

</body>
</html>
