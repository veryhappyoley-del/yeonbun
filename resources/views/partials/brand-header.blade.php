{{--
  브랜드 헤더 (2026-09-28 신설) — UI 전달본 .brand-header.
  홈처럼 "첫 화면"에서 브랜드를 보여주는 자리. 워드마크 + 한 줄 태그라인 + 궤도 마크.

  $tagline : 워드마크 아래 한 줄 (기본값: 서비스 태그라인)
--}}
<header class="brand-header">
  <div>
    <div class="wordmark">연록</div>
    <p>{{ $tagline ?? '사주로 읽는 나의 연애' }}</p>
  </div>
  {{-- (2026-09-28) 궤도 마크도 사용자가 전달한 투명 배경 PNG로 교체. --}}
  <img src="{{ asset('img/handoff/orbit-mark.png') }}" width="320" height="250"
       alt="" aria-hidden="true" decoding="async">
</header>
