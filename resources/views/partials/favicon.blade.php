{{--
  파비콘/앱 아이콘 파셜 (2026-08-25 신설, 브랜드 로고 적용 작업).

  사용자가 로고 제작 AI(도장 브러시 아이콘 + "연록" 워드마크)로 만든 이미지를 잘라서
  public/images/logo/*.png, public/favicon.ico 등으로 만들어뒀다. 여기서 그 파일들을
  전부 <head>에 연결한다 — 공유 레이아웃이 없는 구조라(각 뷰가 자기 <head>를 따로 갖고
  있음) 반복되는 <link> 태그 뭉치를 이 파셜 하나로 뽑아서 각 뷰의 <head> 안에
  @include('partials.favicon')로 불러 쓰는 식으로 관리한다.

  아이콘 자체(favicon.ico, apple-touch-icon, android-chrome-*)는 라이트 모드
  --paper/--ink/--seal 색으로 고정해서 만들었다 — 파비콘은 OS 다크모드에 따라
  색이 안 바뀌므로(브라우저 탭 배경색이 다양해서) 안전하게 종이색 배경 하나로
  고정. theme-color 메타만 라이트/다크 각각의 --paper 값으로 갈라뒀다.
--}}
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#ede6d6" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1b1712" media="(prefers-color-scheme: dark)">
