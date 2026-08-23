<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>결제 완료 — 결</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="wrap wrap-narrow">

  <div class="topbar">
    <a class="chip-link" href="{{ route('billing.index') }}">&larr; 코인 충전 페이지로</a>
  </div>

  <div class="card" style="margin-top:18px;">
    <div class="complete-badge" aria-hidden="true">✓</div>
    <div class="complete-title">
      <h2>결제가 완료됐어요</h2>
      <p>코인 {{ $payment->credits }}개가 충전됐어요. 이제 연애 코치와 이야기할 수 있어요.</p>
    </div>

    <dl class="receipt">
      <div class="receipt-row">
        <dt>상품</dt>
        <dd>{{ $plan['label'] ?? $payment->plan }} ({{ $payment->credits }}개)</dd>
      </div>
      <div class="receipt-row">
        <dt>결제수단</dt>
        <dd>카드</dd>
      </div>
      <div class="receipt-row">
        <dt>주문번호</dt>
        <dd style="font-size:0.78rem;">{{ $payment->order_id }}</dd>
      </div>
      <div class="receipt-row">
        <dt>결제일시</dt>
        <dd>{{ $payment->updated_at->format('Y.m.d H:i') }}</dd>
      </div>
      <div class="receipt-row total">
        <dt>결제금액</dt>
        <dd>{{ number_format($payment->amount) }}원</dd>
      </div>
    </dl>

    <div class="placeholder-note" style="margin-top:0;">
      지금 남은 메시지: <strong>{{ $credits }}개</strong>
    </div>

    <div class="complete-actions">
      <a class="btn" href="{{ route('home') }}">코치와 대화하러 가기</a>
      <a class="btn outline" href="{{ route('billing.index') }}">코인 더 충전하기</a>
    </div>
  </div>

  <footer>
    영수증(결제 상세 내역)은 토스페이먼츠 결제 확인 메일로도 함께 발송돼요.
    @include('partials.business-footer')
  </footer>
</div>

</body>
</html>
