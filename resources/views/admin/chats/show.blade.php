<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>상담 보기 — 관리자 대시보드</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="wrap wrap-narrow">

  <div class="topbar">
    <a class="chip-link" href="{{ route('admin.chats') }}">&larr; 상담 목록으로</a>
  </div>

  <div class="hero">
    <div class="hero-text">
      <h1>{{ $chatSession->user->name ?? '탈퇴한 사용자' }}님의 상담</h1>
      <p>
        {{ $chatSession->created_at->format('Y-m-d H:i') }} 시작 ·
        메시지 {{ $chatSession->messages->count() }}개
      </p>
    </div>
  </div>

  <div class="card">
    @if ($chatSession->messages->isEmpty())
      <div class="empty-state">아직 오간 메시지가 없어요.</div>
    @else
      <div class="chat-transcript">
        @php $lastDate = null; @endphp
        @foreach ($chatSession->messages as $message)
          @php $currentDate = $message->created_at->format('Y-m-d'); @endphp
          @if ($currentDate !== $lastDate)
            <div class="chat-date-divider">{{ $message->created_at->format('Y년 n월 j일') }}</div>
            @php $lastDate = $currentDate; @endphp
          @endif
          <div class="chat-turn chat-turn-{{ $message->role === 'user' ? 'user' : 'assistant' }}">
            <div class="chat-bubble chat-{{ $message->role === 'user' ? 'user' : 'assistant' }}">{{ $message->content }}</div>
            <div class="chat-meta">{{ $message->created_at->format('H:i') }}</div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <footer>
    이 페이지는 관리자만 볼 수 있어요. 상담 내용에는 사용자의 개인적인 이야기가 담겨 있으니 서비스 품질 점검·오남용 확인 등 꼭 필요한 목적으로만 열람해 주세요.
  </footer>
</div>

</body>
</html>
