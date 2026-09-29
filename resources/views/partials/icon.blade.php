{{--
  icon 파셜 (2026-09-19 신설) — 개편 시안의 "얇은 선 아이콘" 규격.
  24x24 viewBox, stroke-width 1.6, currentColor. 장식용 이모지를 UI 아이콘으로 쓰던
  자리를 이 파셜로 교체했다(시안 규칙: 아이콘은 얇은 선과 일정한 크기로 통일).

  사용: @include('partials.icon', ['name' => 'heart'])
    $name  : 아이콘 키
    $class : 추가 클래스 (크기는 .yk-icon / 사용처 CSS에서 제어)
--}}
@php
  $name = $name ?? 'sparkle';
  $class = $class ?? '';
  $paths = [
    'heart' => '<path d="M12 19.5s-6.8-4.2-6.8-9A3.9 3.9 0 0 1 12 8.3a3.9 3.9 0 0 1 6.8 2.2c0 4.8-6.8 9-6.8 9Z"/>',
    'chat' => '<path d="M20 12.3c0 3.6-3.6 6.5-8 6.5-.9 0-1.8-.1-2.6-.35L5 20l1-3.1A6.2 6.2 0 0 1 4 12.3c0-3.6 3.6-6.5 8-6.5s8 2.9 8 6.5Z"/><path d="M9.2 12h.01M12 12h.01M14.8 12h.01"/>',
    'book' => '<path d="M12 7.2c-1.3-1-3.1-1.5-4.9-1.5-1 0-1.4.15-1.4.5v11.3c0 .3.4.5 1 .5 1.7 0 3.5.5 5.3 1.5"/><path d="M12 7.2c1.3-1 3.1-1.5 4.9-1.5 1 0 1.4.15 1.4.5v11.3c0 .3-.4.5-1 .5-1.7 0-3.5.5-5.3 1.5V7.2Z"/>',
    'infinity' => '<path d="M9.4 12c0 1.9-1.4 3.3-3.1 3.3S3.2 13.9 3.2 12s1.4-3.3 3.1-3.3c2.9 0 4.3 6.6 7.2 6.6 1.7 0 3.1-1.4 3.1-3.3s-1.4-3.3-3.1-3.3c-2.9 0-4.3 6.6-7.2 6.6"/>',
    'compass' => '<circle cx="12" cy="12" r="8"/><path d="m14.6 9.4-1.5 4.1-4.1 1.5 1.5-4.1 4.1-1.5Z"/>',
    'check' => '<path d="M5 12l4 4L19 6"/>',
    // (2026-09-28 추가) "PDF로 저장"이 공유 아이콘을 쓰고 있어 동작과 어긋났다.
    'download' => '<path d="M12 4v11"/><path d="m7.6 10.6 4.4 4.4 4.4-4.4"/><path d="M5 19.2h14"/>',
    'lock' => '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/>',
    'arrow-right' => '<path d="M5 12h13M13 6.8 18.2 12 13 17.2"/>',
    'sparkle' => '<path d="M12 4.5c1 4.4 2.1 5.5 6.5 6.5-4.4 1-5.5 2.1-6.5 6.5-1-4.4-2.1-5.5-6.5-6.5 4.4-1 5.5-2.1 6.5-6.5Z"/>',
    'calendar' => '<rect x="4" y="6" width="16" height="14" rx="2.4"/><path d="M4 10.4h16M8.6 4v3.4M15.4 4v3.4"/>',
    'chart' => '<path d="M4 20V12M9 20V7M14 20V10M19 20V4"/>',
    'shield' => '<path d="M12 3l8 3v6c0 5-3 8-8 10-5-2-8-5-8-10V6l8-3z"/><path d="M8 12l3 3 5-6"/>',
    'refresh' => '<path d="M19 12a7 7 0 1 1-2.1-5"/><path d="M19.2 5v4.3h-4.3"/>',
    'search' => '<circle cx="11" cy="11" r="6"/><path d="m15.5 15.5 3.5 3.5"/>',
    'bulb' => '<path d="M9.4 17.2a5.4 5.4 0 1 1 5.2 0"/><path d="M9.8 17.2h4.4M10.5 20h3"/>',
    'coin' => '<circle cx="12" cy="12" r="7.5"/><path d="M12 8.2v7.6M14 9.8c-.5-.7-1.3-1-2-1-1.1 0-2 .6-2 1.6 0 2.2 4 1.2 4 3.4 0 1-.9 1.6-2 1.6-.8 0-1.6-.3-2.1-1.1"/>',
    'doc' => '<path d="M13.4 4.5H7.6A1.6 1.6 0 0 0 6 6.1v11.8a1.6 1.6 0 0 0 1.6 1.6h8.8a1.6 1.6 0 0 0 1.6-1.6V9.1l-4.6-4.6Z"/><path d="M13.2 4.7v4.3h4.5M9.4 13h5.2M9.4 16h3.4"/>',
    'mail' => '<rect x="4" y="6.2" width="16" height="11.6" rx="2.2"/><path d="m4.8 7.6 7.2 5 7.2-5"/>',
    'star' => '<path d="m12 5 2.1 4.6 5 .6-3.7 3.4 1 4.9L12 16.1 7.6 18.5l1-4.9L4.9 10.2l5-.6L12 5Z"/>',
    'close' => '<path d="m7 7 10 10M17 7 7 17"/>',
    'alert' => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4.6M12 15.6h.01"/>',
    'chevron-right' => '<path d="M9 5l7 7-7 7"/>',
    'share' => '<path d="M12 15V3m0 0L8 7m4-4l4 4M5 11v9h14v-9"/>',
    'home' => '<path d="M3 11l9-8 9 8v9a1 1 0 01-1 1h-5v-7H9v7H4a1 1 0 01-1-1v-9z" stroke-linejoin="round"/>',
    'relation' => '<circle cx="9" cy="12" r="6.5"/><circle cx="15" cy="12" r="6.5"/>',
    'record' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
    'back' => '<path d="M15 4l-8 8 8 8"/>',
    'chevron' => '<path d="M9 5l7 7-7 7"/>',
    'user' => '<circle cx="12" cy="8" r="3.6"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
  ];
@endphp
<svg class="yk-icon {{ $class }}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" focusable="false">{!! $paths[$name] ?? $paths['sparkle'] !!}</svg>
