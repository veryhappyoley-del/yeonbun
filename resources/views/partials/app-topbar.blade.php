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
  $titleTag    : 제목 태그 (기본 h1)

  (2026-09-28 수정) 제목을 <strong>에서 <h1>로 바꿨다. 개편하면서 기존 히어로의 h1을
  이 상단바로 대체했는데 <strong>은 heading이 아니라서, 상단바를 쓰는 화면 12개가
  h1 없이 h2/h3부터 시작하게 됐다(스크린리더 제목 건너뛰기 불가, 공개 페이지 SEO 손해).
  한 화면에 h1이 이미 따로 있는 경우를 위해 $titleTag로 레벨을 낮출 수 있게 열어둔다.
--}}
<header class="app-topbar">
  @if (!empty($back))
    <a class="icon-button" href="{{ $back }}" aria-label="{{ $backLabel ?? '뒤로' }}">
      @include('partials.icon', ['name' => 'back'])
    </a>
  @else
    <span aria-hidden="true"></span>
  @endif

  @php $tag = $titleTag ?? 'h1'; @endphp
  <{{ $tag }} class="app-topbar-title">{{ $title }}</{{ $tag }}>

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
