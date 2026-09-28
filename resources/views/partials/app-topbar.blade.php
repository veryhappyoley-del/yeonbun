{{--
  상단바 (2026-09-28 신설) — UI 전달본 .topbar.
  하위 화면에서 "뒤로 + 화면 제목 + (선택)오른쪽 액션" 세 칸 그리드로 쓴다.

  $title       : 가운데 제목 (필수)
  $back        : 뒤로 갈 URL (없으면 왼쪽 칸을 비움)
  $backLabel   : 뒤로 버튼의 접근성 이름 (기본 "뒤로")
  $actionIcon  : 오른쪽 아이콘 버튼의 아이콘 키 (partials/icon.blade.php)
  $actionLabel : 오른쪽 버튼의 접근성 이름
  $actionId    : 오른쪽 버튼의 id (JS가 잡아야 할 때)
  $actionHref  : 링크로 동작해야 할 때의 URL (없으면 <button>)
--}}
<header class="app-topbar">
  @if (!empty($back))
    <a class="icon-button" href="{{ $back }}" aria-label="{{ $backLabel ?? '뒤로' }}">
      @include('partials.icon', ['name' => 'back'])
    </a>
  @else
    <span aria-hidden="true"></span>
  @endif

  <strong>{{ $title }}</strong>

  @if (!empty($actionIcon))
    @if (!empty($actionHref))
      <a class="icon-button" href="{{ $actionHref }}" aria-label="{{ $actionLabel ?? '' }}"
         @if (!empty($actionId)) id="{{ $actionId }}" @endif>
        @include('partials.icon', ['name' => $actionIcon])
      </a>
    @else
      <button type="button" class="icon-button" aria-label="{{ $actionLabel ?? '' }}"
              @if (!empty($actionId)) id="{{ $actionId }}" @endif>
        @include('partials.icon', ['name' => $actionIcon])
      </button>
    @endif
  @else
    <span aria-hidden="true"></span>
  @endif
</header>
