{{--
  사전 (2026-08-24 신설, 헤더/하단탭 도입 작업) — 하단 탭바 "사전"의 목적지. 리포트 안에
  남을 수 있는 명리학 전문 용어를 쉬운 말로 풀어둔 공개 페이지.

  ChapterGenerator::prompt()에 이미 "전문 용어를 그대로 나열하지 말고 쉬운 말로 풀어써라"는
  지침을 넣어뒀지만(2026-08-24), 그래도 남을 수 있는 용어에 대한 이중 안전장치이자, 로그인
  없이 검색엔진도 볼 수 있는 공개 콘텐츠라 "편관 뜻", "신강신약이란" 같은 검색 유입도
  기대할 수 있다. 지금은 핵심 용어 위주로 채워두고, 나중에 리포트에서 실제로 자주 나오는
  용어를 보면서 추가하면 된다.
--}}
@php
  $dictSections = [
      [
          'title' => '기본 개념',
          'terms' => [
              ['term' => '일간', 'desc' => '태어난 날의 천간(하늘 기운) 한 글자. 사주 여덟 글자 중 "나 자신"을 상징하는 글자예요. 다른 모든 풀이가 이 글자를 기준으로 이루어져요.'],
              ['term' => '오행', 'desc' => '목(木)·화(火)·토(土)·금(金)·수(水) 다섯 가지 기운. 사주에 어떤 기운이 많고 적은지로 전체적인 성향을 설명하는 기본 틀이에요.'],
              ['term' => '신강 / 신약', 'desc' => '내 사주에서 일간(나 자신)의 기운이 강한 편인지 약한 편인지를 뜻해요. 신강이면 주도적이고 뚝심 있는 쪽으로, 신약이면 주변에 잘 맞추고 기대는 쪽으로 풀이되는 경우가 많아요.'],
              ['term' => '십신', 'desc' => '일간을 기준으로 다른 글자들과의 관계를 열 가지로 분류한 것. 비견·겁재·식신·상관·편재·정재·편관·정관·편인·정인이 있어요.'],
              ['term' => '상생', 'desc' => '오행 중 한 기운이 다른 기운을 자연스럽게 도와주는 관계예요(예: 목생화 — 나무가 불을 키움). 리포트에서 "서로 도와주는 관계"라고 풀어 쓰는 경우가 많아요.'],
              ['term' => '상극', 'desc' => '오행 중 한 기운이 다른 기운을 억누르거나 부딪히는 관계예요(예: 목극토 — 나무뿌리가 흙을 파고듦). 리포트에서 "부딪히기 쉬운 관계"라고 풀어 쓰는 경우가 많아요.'],
              ['term' => '궁합', 'desc' => '두 사람의 사주를 비교해서 서로 잘 맞는 정도를 보는 것. 두 사람의 일간·오행이 상생인지 상극인지가 중요한 기준이 돼요.'],
          ],
      ],
      [
          'title' => '십신 열 가지',
          'terms' => [
              ['term' => '비견', 'desc' => '나와 같은 오행, 같은 음양. 독립적이고 자기 주관이 뚜렷한 성향과 연결돼요.'],
              ['term' => '겁재', 'desc' => '나와 같은 오행이지만 음양이 다른 것. 경쟁심이나 승부욕과 연결돼요.'],
              ['term' => '식신', 'desc' => '내가 낳아주는 기운 중 편안한 쪽. 여유롭고 표현력 있는 성향과 연결돼요.'],
              ['term' => '상관', 'desc' => '내가 낳아주는 기운 중 날카로운 쪽. 재치 있는 말솜씨, 강한 자기주장과 연결돼요.'],
              ['term' => '편재', 'desc' => '내가 다스리는 기운 중 유동적인 쪽. 활동적인 재물운, 융통성과 연결돼요.'],
              ['term' => '정재', 'desc' => '내가 다스리는 기운 중 안정적인 쪽. 성실하게 차곡차곡 쌓는 재물운과 연결돼요.'],
              ['term' => '편관', 'desc' => '나를 다스리는(눌리기 쉬운) 기운 중 강한 쪽. 스스로에게 엄격한 잣대를 들이대는 긴장감, 책임감과 연결돼요.'],
              ['term' => '정관', 'desc' => '나를 다스리는 기운 중 안정적인 쪽. 원칙을 지키고 신뢰를 주는 성향과 연결돼요.'],
              ['term' => '편인', 'desc' => '나를 낳아주는(도와주는) 기운 중 독특한 쪽. 남다른 사고방식, 직관과 연결돼요.'],
              ['term' => '정인', 'desc' => '나를 낳아주는 기운 중 안정적인 쪽. 배우고 보살핌받는 것에 능한 성향과 연결돼요.'],
          ],
      ],
      [
          'title' => '운의 흐름',
          'terms' => [
              ['term' => '대운', 'desc' => '몇 년 단위로 크게 바뀌는 인생의 흐름을 말해요. 연록에서는 아직 대운 계산 기능을 제공하지 않아요(추후 지원 예정).'],
              ['term' => '세운', 'desc' => '한 해 단위로 바뀌는 운의 흐름을 말해요. 연록에서는 아직 세운 계산 기능을 제공하지 않아요(추후 지원 예정).'],
          ],
      ],
  ];
@endphp
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>사전 — 연록</title>
  <meta name="description" content="일간, 오행, 신강신약, 십신 등 명리학 기초 용어를 쉬운 말로 풀어드려요.">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>사전</h1>
      <p>리포트에 나오는 명리학 용어, 여기서 쉽게 찾아보세요.</p>
    </div>
  </div>

  @foreach ($dictSections as $section)
    <div class="card">
      <h2>{{ $section['title'] }}</h2>
      <div class="dict-term-list">
        @foreach ($section['terms'] as $t)
          <div class="dict-term">
            <div class="dict-term-name">{{ $t['term'] }}</div>
            <div class="dict-term-desc">{{ $t['desc'] }}</div>
          </div>
        @endforeach
      </div>
    </div>
  @endforeach

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

</body>
</html>
