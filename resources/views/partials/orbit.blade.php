{{--
  orbit 파셜 (2026-09-19 신설) — 개편 시안의 시그니처 장식.
  가는 로즈색 궤도 + 보라/로즈 구체 + 작은 별로 "두 마음의 흐름"을 표현한다.

  원본 디자인 파일(ai/psd)이 프로젝트에 없어서, 시안의 형태를 SVG로 다시 그린 대체
  자산이다. 이미지 파일이 아니라 인라인 SVG라 팔레트 토큰(currentColor/var())을 그대로
  따라가고, 다크 모드와 확대에도 깨지지 않는다.

  파라미터
    $variant : 'infinity'(히어로) | 'orbit'(교차 궤도) | 'ellipse'(단일 궤도) | 'doc'(문서)
    $size    : CSS width (기본 100%)
    $class   : 추가 클래스

  장식 전용이라 aria-hidden 처리하고 클릭을 가로채지 않도록 pointer-events를 끈다
  (.orbit-art 규칙에서 처리).
--}}
@php
  $variant = $variant ?? 'infinity';
  $uid = 'orb' . substr(md5($variant . uniqid('', true)), 0, 6);
  $class = $class ?? '';
@endphp
<svg class="orbit-art orbit-art--{{ $variant }} {{ $class }}" viewBox="0 0 220 140"
     fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
  <defs>
    <radialGradient id="{{ $uid }}-p" cx="32%" cy="28%" r="78%">
      <stop offset="0%" stop-color="var(--orbit-plum-hi, #9E7C9C)"/>
      <stop offset="55%" stop-color="var(--orbit-plum, #70516F)"/>
      <stop offset="100%" stop-color="var(--orbit-plum-lo, #4C3450)"/>
    </radialGradient>
    <radialGradient id="{{ $uid }}-r" cx="32%" cy="28%" r="78%">
      <stop offset="0%" stop-color="var(--orbit-rose-hi, #D89AA2)"/>
      <stop offset="60%" stop-color="var(--orbit-rose, #A65D67)"/>
      <stop offset="100%" stop-color="var(--orbit-rose-lo, #8A4750)"/>
    </radialGradient>
  </defs>

  @if ($variant === 'infinity')
    <path d="M32 78C32 44 62 40 84 63c22 23 52 27 62 4 10-23-12-44-34-33-22 11-30 52-58 60-20 6-22-10-22-16Z"
          stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" stroke-linecap="round" opacity="0.85"/>
    <circle cx="60" cy="96" r="17" fill="url(#{{ $uid }}-p)"/>
    <circle cx="163" cy="47" r="9.5" fill="url(#{{ $uid }}-r)"/>
    <circle cx="103" cy="66" r="3.2" fill="var(--orbit-dot, #6D4A52)"/>
  @elseif ($variant === 'orbit')
    <ellipse cx="110" cy="70" rx="62" ry="30" transform="rotate(-24 110 70)"
             stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" opacity="0.85"/>
    <ellipse cx="110" cy="70" rx="62" ry="30" transform="rotate(26 110 70)"
             stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" opacity="0.5"/>
    <circle cx="66" cy="88" r="14" fill="url(#{{ $uid }}-p)"/>
    <circle cx="160" cy="49" r="8" fill="url(#{{ $uid }}-r)"/>
  @elseif ($variant === 'ellipse')
    <ellipse cx="110" cy="70" rx="66" ry="26" transform="rotate(-18 110 70)"
             stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" opacity="0.85"/>
    <circle cx="58" cy="80" r="12" fill="url(#{{ $uid }}-p)"/>
    <circle cx="164" cy="58" r="7" fill="url(#{{ $uid }}-r)"/>
  @else
    <rect x="80" y="34" width="62" height="74" rx="8"
          stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" opacity="0.7"/>
    <path d="M94 56h34M94 70h34M94 84h22" stroke="var(--orbit-line, #C9909A)"
          stroke-width="1.4" stroke-linecap="round" opacity="0.55"/>
    <ellipse cx="110" cy="70" rx="72" ry="34" transform="rotate(-20 110 70)"
             stroke="var(--orbit-line, #C9909A)" stroke-width="1.4" opacity="0.6"/>
    <circle cx="44" cy="82" r="11" fill="url(#{{ $uid }}-p)"/>
    <circle cx="176" cy="52" r="7" fill="url(#{{ $uid }}-r)"/>
  @endif

  {{-- 네 갈래 별(시안의 반짝임) --}}
  <path d="M186 22c1.6 5.6 2.8 6.8 8.4 8.4-5.6 1.6-6.8 2.8-8.4 8.4-1.6-5.6-2.8-6.8-8.4-8.4 5.6-1.6 6.8-2.8 8.4-8.4Z"
        fill="var(--orbit-star, #C9909A)" opacity="0.75"/>
</svg>
