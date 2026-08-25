{{--
  "사주" 종목 고르기 페이지 (2026-08-24 신설).

  배경: 헤더/하단 탭바 작업을 시작하면서, 하단 탭 "사주"를 누르면 바로 계산기(/`saju.blade.php`)로
  들어가지 않고 이 페이지를 먼저 거치도록 구조를 바꿨다. 지금은 연애 관련 종목(나의 연애 사주/
  궁합 보기/연애 코치)만 있지만, 나중에 재회전략/재물성장전략/직업성공전략/연간전략/인생전략까지
  늘어날 걸 감안해서(계획 문서 "챕터형 프리미엄 리포트 아키텍처" 참고) 처음부터 카테고리로 나눠
  둔다. 사용자가 참고로 준 경쟁사(명사도) 화면처럼 상단에 카테고리 필터 + 그 아래 종목 카드
  목록으로 구성하되, 색/타이포는 명사도의 어두운 네온 톤이 아니라 우리 사이트 결(종이/먹색/
  인주색 seal, Song Myung 세리프)을 그대로 따른다.

  카테고리별 종목 데이터는 지금은 이 파일 안에 정적으로 정의한다 — ReportTypeRegistry(연애운분석/
  궁합분석)와 100% 겹치지 않는 이유는, 이 페이지의 "종목"에는 리포트 타입이 아닌 것(연애 코치는
  코인 소모형 AI 상담이지 리포트가 아님)도 섞여 있고, 아직 리포트 타입으로 등록되지 않은 준비중
  항목(재회 전략 등)도 보여줘야 하기 때문이다. 나중에 종목이 늘어나 이 파일이 너무 길어지면 그때
  컨트롤러+데이터 클래스로 옮기면 된다.
--}}
@php
  $saguCategories = [
      [
          'key' => 'love',
          'label' => '연애 · 재회',
          'available' => true,
          'items' => [
              [
                  'badge' => '사', 'title' => '나의 연애 사주',
                  'desc' => '내 사주로 보는 연애 기질과 성향, 그리고 앞으로의 연애운.',
                  'href' => route('calculator.index', ['tab' => 'single']), 'price' => '무료로 시작',
              ],
              [
                  'badge' => '궁', 'title' => '궁합 보기',
                  'desc' => '두 사람의 사주로 보는 궁합 점수와 관계의 흐름.',
                  'href' => route('calculator.index', ['tab' => 'compat']), 'price' => '무료로 시작',
              ],
              [
                  'badge' => '코', 'title' => '연애 코치',
                  'desc' => '내 사주 맥락을 아는 AI 코치와 실시간으로 연애 상담.',
                  'href' => route('calculator.index', ['tab' => 'chat']), 'price' => '코인으로 상담',
              ],
              [
                  'badge' => '재', 'title' => '재회 전략',
                  'desc' => '헤어진 관계를 다시 잇고 싶을 때를 위한 전략 리포트.',
                  'comingSoon' => true,
              ],
          ],
      ],
      [
          'key' => 'life',
          'label' => '평생 · 연간',
          'available' => false,
      ],
      [
          'key' => 'wealth',
          'label' => '재물 · 커리어',
          'available' => false,
      ],
  ];
@endphp
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>사주 — 연록</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @include('partials.favicon')
</head>
<body class="phone-app has-bottom-nav">

<div class="wrap wrap-narrow">

  {{-- (2026-08-24 수정) 하단 탭바가 생겨서 "결로 돌아가기" 링크는 더 이상 필요 없음(하단
       "홈" 탭이 그 역할을 대신함) — 전역 헤더로 교체. --}}
  @include('partials.site-header')

  <div class="hero">
    <svg class="seal-mark" viewBox="0 0 64 64" aria-hidden="true">
      <rect x="4" y="4" width="56" height="56" rx="8" fill="none" stroke="var(--seal)" stroke-width="3"></rect>
      <text x="32" y="39" text-anchor="middle" font-family="Song Myung, serif" font-size="19" letter-spacing="-0.5" fill="var(--seal)">연록</text>
    </svg>
    <div class="hero-text">
      <h1>사주</h1>
      {{-- (2026-08-25 6차 수정) 원래 "카테고리는 앞으로 계속 늘어날 예정이에요"였는데,
           사용자 지적: 내부 로드맵을 사용자에게 알릴 필요가 없다 — 대신 브랜드 정체성을
           설명하는 문구로 교체. 홈페이지 "결의 차별점" 섹션과 같은 메시지("인생 전반이
           아니라 연애·궁합만 깊이 파고든다")를 재사용해서 사이트 전체 톤을 일관되게 뒀다. --}}
      <p>지금 궁금한 걸 골라주세요. 연록은 인생 전반이 아니라 연애와 궁합에만 집중해요.</p>
    </div>
  </div>

  <div class="sagu-cat-row" role="tablist">
    @foreach ($saguCategories as $i => $cat)
      <button type="button" class="sagu-cat-btn @if ($i === 0) active @endif" data-cat="{{ $cat['key'] }}" role="tab">{{ $cat['label'] }}</button>
    @endforeach
  </div>

  @foreach ($saguCategories as $i => $cat)
    <div class="sagu-panel @if ($i === 0) active @endif" id="sagu-panel-{{ $cat['key'] }}">
      @if ($cat['available'])
        <div class="sagu-card-list">
          @foreach ($cat['items'] as $item)
            @if (! empty($item['comingSoon']))
              <div class="sagu-card sagu-card--soon">
                <span class="sagu-card-badge" aria-hidden="true">{{ $item['badge'] }}</span>
                <div class="sagu-card-body">
                  <div class="sagu-card-title">{{ $item['title'] }}</div>
                  <div class="sagu-card-desc">{{ $item['desc'] }}</div>
                </div>
                <span class="sagu-card-price">준비 중</span>
              </div>
            @else
              <a class="sagu-card" href="{{ $item['href'] }}">
                <span class="sagu-card-badge" aria-hidden="true">{{ $item['badge'] }}</span>
                <div class="sagu-card-body">
                  <div class="sagu-card-title">{{ $item['title'] }}</div>
                  <div class="sagu-card-desc">{{ $item['desc'] }}</div>
                </div>
                <span class="sagu-card-price">{{ $item['price'] }}</span>
              </a>
            @endif
          @endforeach
        </div>
      @else
        <div class="sagu-empty">이 카테고리는 아직 준비 중이에요. 곧 만나요!</div>
      @endif
    </div>
  @endforeach

  @include('partials.business-footer')

</div>

@include('partials.site-bottom-nav')

<script>
  document.querySelectorAll('.sagu-cat-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.sagu-cat-btn').forEach(function (b) { b.classList.remove('active'); });
      document.querySelectorAll('.sagu-panel').forEach(function (p) { p.classList.remove('active'); });
      btn.classList.add('active');
      document.getElementById('sagu-panel-' + btn.getAttribute('data-cat')).classList.add('active');
    });
  });
</script>

</body>
</html>
