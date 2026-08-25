<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>코인 충전 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @if ($tossConfigured)
    <script src="https://js.tosspayments.com/v1/payment"></script>
  @endif
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap">

  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>코인 충전</h1>
      <p>연애 코치와 대화할 수 있는 메시지를 충전해요.</p>
      <p class="sub">지금 남은 메시지: <strong>{{ $credits }}개</strong></p>
    </div>
  </div>

  @if (! $tossConfigured && $isTestMode)
    <div class="placeholder-note">
      <strong>테스트 모드</strong> — 아직 토스페이먼츠 키가 설정되지 않아서, 결제 없이 바로 크레딧이 지급되는 로컬 테스트 방식으로 동작해요. <code>.env</code>에 <code>TOSS_CLIENT_KEY</code>/<code>TOSS_SECRET_KEY</code>를 넣으면 실제 결제창이 뜨는 방식으로 자동 전환돼요.
    </div>
  @endif

  @if ($tossConfigured && $isTestMode)
    <div class="placeholder-note">
      <strong>토스 테스트 키 연결됨</strong> — 실제 결제창이 뜨지만, <code>test_</code>로 시작하는 키를 쓰고 있다면 결제수단에서 실제로 돈이 빠져나가지 않아요.
    </div>
  @endif

  <div id="billing-error" style="display:none;" class="card">
    <span id="billing-error-text"></span>
  </div>

  @if (session('billing_error'))
    <div class="card" style="border-color: var(--seal); color: var(--seal-deep);">
      {{ session('billing_error') }}
    </div>
  @endif

  <div class="plan-grid">
    @foreach ($plans as $key => $plan)
      @if ($tossConfigured)
        <div class="plan-card @if(!empty($plan['highlight'])) highlight @endif">
          @if (!empty($plan['highlight']))
            <div class="plan-badge">가장 인기</div>
          @endif
          <div class="plan-name">{{ $plan['label'] }}</div>
          <div class="plan-credits">{{ $plan['credits'] }}<span>개</span></div>
          <div class="plan-desc">{{ $plan['desc'] }}</div>
          <div class="plan-price">{{ number_format($plan['price']) }}원</div>
          <div class="plan-unit">메시지 1개당 약 {{ number_format($plan['price'] / $plan['credits']) }}원</div>
          <button type="button" class="btn plan-buy" data-plan="{{ $key }}">결제하기</button>
        </div>
      @else
        <form method="POST" action="{{ route('billing.purchase') }}" class="plan-card @if(!empty($plan['highlight'])) highlight @endif">
          @csrf
          <input type="hidden" name="plan" value="{{ $key }}">
          @if (!empty($plan['highlight']))
            <div class="plan-badge">가장 인기</div>
          @endif
          <div class="plan-name">{{ $plan['label'] }}</div>
          <div class="plan-credits">{{ $plan['credits'] }}<span>개</span></div>
          <div class="plan-desc">{{ $plan['desc'] }}</div>
          <div class="plan-price">{{ number_format($plan['price']) }}원</div>
          <div class="plan-unit">메시지 1개당 약 {{ number_format($plan['price'] / $plan['credits']) }}원</div>
          <button type="submit" class="btn">[테스트] 충전하기</button>
        </form>
      @endif
    @endforeach
  </div>

  <div class="placeholder-note">
    코인은 연애 코치와의 대화(AI 응답 1회당 1개)에만 쓰여요. 사주 계산·궁합·상담가이드는 코인과 상관없이 계속 무료예요.
  </div>

  <footer>
    표시된 가격은 예시 값이며, 자유롭게 조정하실 수 있어요. 정기결제(구독)는 제공하지 않고 건별 결제만 지원해요.
    @include('partials.business-footer')
  </footer>
</div>

@include('partials.site-bottom-nav')

@if ($tossConfigured)
<script>
(function () {
  var clientKey = @json($tossClientKey);
  var customerName = @json(auth()->user()->name);
  var tossPayments = TossPayments(clientKey);

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function showError(message) {
    document.getElementById('billing-error-text').textContent = message;
    document.getElementById('billing-error').style.display = 'block';
  }

  document.querySelectorAll('.plan-buy').forEach(function (button) {
    button.addEventListener('click', function () {
      button.disabled = true;

      fetch('{{ route('billing.checkout') }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ plan: button.dataset.plan })
      })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
          if (!r.ok) throw new Error(r.body.error || '결제를 시작하지 못했어요.');

          return tossPayments.requestPayment('카드', {
            amount: r.body.amount,
            orderId: r.body.order_id,
            orderName: r.body.order_name,
            customerName: customerName,
            successUrl: '{{ route('billing.success') }}',
            failUrl: '{{ route('billing.fail') }}'
          });
        })
        .catch(function (error) {
          if (error && error.code === 'USER_CANCEL') return; // 사용자가 결제창을 직접 닫은 경우
          showError((error && error.message) || '결제 중 문제가 발생했어요.');
        })
        .finally(function () {
          button.disabled = false;
        });
    });
  });
})();
</script>
@endif

</body>
</html>
