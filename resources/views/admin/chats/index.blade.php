<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>상담 내용 — 관리자 대시보드</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>

<div class="wrap">

  <div class="topbar">
    <a class="chip-link" href="{{ route('home') }}">&larr; 연록으로 돌아가기</a>
  </div>

  <div class="hero">
    <div class="hero-text">
      <h1>상담 내용</h1>
      <p>사용자들이 연애 코치와 나눈 대화를 확인해요.</p>
    </div>
  </div>

  <div class="admin-tabs">
    <a href="{{ route('admin.dashboard') }}" class="admin-tab">대시보드</a>
    <a href="{{ route('admin.chats') }}" class="admin-tab active">상담 내용</a>
  </div>

  <form method="GET" action="{{ route('admin.chats') }}" class="filter-row">
    <div class="filter-field">
      <label for="user">사용자</label>
      <input type="text" id="user" name="user" value="{{ $userKeyword }}" placeholder="이름 또는 이메일">
    </div>
    <div class="filter-field">
      <label for="st_date">시작일</label>
      <input type="date" id="st_date" name="st_date" value="{{ $stDate }}" @if($edDate) max="{{ $edDate }}" @endif>
    </div>
    <div class="filter-field">
      <label for="ed_date">종료일</label>
      <input type="date" id="ed_date" name="ed_date" value="{{ $edDate }}" @if($stDate) min="{{ $stDate }}" @endif>
    </div>
    <button type="submit" class="filter-submit">검색</button>
    @if ($hasFilters)
      <a href="{{ route('admin.chats') }}" class="filter-reset">필터 지우기</a>
    @endif
  </form>

  <div class="card">
    @if ($sessions->isEmpty())
      <div class="empty-state">
        @if ($hasFilters)
          조건에 맞는 상담 세션이 없어요.
        @else
          아직 상담 세션이 없어요.
        @endif
      </div>
    @else
      @if (! $hasFilters)
        <p class="chart-note">최근 상담 {{ $sessions->count() }}건을 보여드려요. 더 찾으려면 위에서 검색해 보세요.</p>
      @endif

      <div class="chat-session-list">
        @foreach ($sessions as $session)
          <a href="{{ route('admin.chats.show', $session) }}" class="chat-session-row">
            <div class="chat-session-main">
              <div class="chat-session-user">{{ $session->user->name ?? '탈퇴한 사용자' }}</div>
              <div class="chat-session-preview">
                {{ $session->lastMessage ? \Illuminate\Support\Str::limit($session->lastMessage->content, 60) : '(대화 없음)' }}
              </div>
            </div>
            <div class="chat-session-meta">
              <span>메시지 {{ $session->messages_count }}개</span>
              <span>{{ $session->updated_at->diffForHumans() }}</span>
            </div>
          </a>
        @endforeach
      </div>

      @if ($hasFilters)
        <div style="margin-top: 18px;">
          {{ $sessions->links() }}
        </div>
      @endif
    @endif
  </div>

  <footer>
    상담 내용에는 사용자의 개인적인 이야기가 담겨 있어요. 서비스 품질 점검·오남용 확인 등 꼭 필요한 목적으로만 열람해 주세요.
  </footer>
</div>

</body>
</html>
