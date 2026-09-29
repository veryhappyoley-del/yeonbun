{{--
  챕터형(schema_version=2) 리포트의 목차. 두 부분으로 구성됩니다:
  1) 상단에 sticky로 붙는 가로 번호 탭 스트립(01/02/03…) — 스크롤하는 동안 현재 보고
     있는 챕터가 public/js/report-toc.js(IntersectionObserver)에 의해 강조됩니다.
  2) 전체 챕터를 세로로 나열하는 목차 목록(제목 + 티저 + 상태 뱃지) — 클릭하면 해당
     섹션으로 스크롤됩니다(앵커 이동 + CSS의 scroll-behavior:smooth).

  기대하는 변수:
  - $report: App\Models\Report, chapters 관계가 이미 로드되어 있어야 함(N+1 방지).
  - $type: App\ReportTypes\ReportType, ReportTypeRegistry::get($report->type)로 얻음.

  아직 ReportTypeRegistry에 등록된 타입이 없어서(4단계에서 추가 예정) 실제로는 어디서도
  include되지 않습니다 — 4~5단계에서 reports.show가 schema_version에 따라 이 파일과
  chapter-reader.blade.php를 함께 include하도록 연결됩니다.
--}}
@php
  $rows = $report->chapters->keyBy('chapter_key');
  $statusLabel = ['pending' => '준비 중', 'generating' => '생성 중', 'ready' => '완료', 'failed' => '재시도 필요'];
@endphp

<div id="chapter-toc" class="chapter-toc-wrap" data-status-url="{{ route('reports.status', $report) }}">

  {{-- (2026-09-28) 이 스트립은 탭 위젯이 아니라 "지금 보고 있는 챕터"를 가리키는 링크
       모음이다. 그래서 role="tab"이 아니라 nav + aria-current(public/js/report-toc.js가
       스크롤에 따라 갱신)를 쓴다. 보이는 글자는 "01"뿐이라 링크 이름만으로는 어디로
       가는지 알 수 없어서, 보이는 글자로 시작하는 aria-label을 함께 준다. --}}
  <nav class="chapter-tab-strip" aria-label="챕터 바로가기">
    @foreach ($type->chapters as $i => $chapter)
      @php $num = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT); @endphp
      <a href="#chapter-{{ $chapter->key }}" class="chapter-tab" data-chapter-tab="{{ $chapter->key }}"
         aria-label="{{ $num }} {{ $chapter->title }}">{{ $num }}</a>
    @endforeach
  </nav>

  <ol class="chapter-toc-list">
    @foreach ($type->chapters as $i => $chapter)
      @php
        $row = $rows->get($chapter->key);
        $status = $row->status ?? 'pending';
      @endphp
      <li class="chapter-toc-item">
        <a href="#chapter-{{ $chapter->key }}">
          <span class="chapter-toc-num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>
          <span class="chapter-toc-body">
            <span class="chapter-toc-title">{{ $chapter->title }}</span>
            @if ($chapter->teaser)
              <span class="chapter-toc-teaser">{{ $chapter->teaser }}</span>
            @endif
          </span>
          <span class="badge chapter-toc-badge chapter-toc-badge--{{ $status }}">{{ $statusLabel[$status] ?? $status }}</span>
        </a>
      </li>
    @endforeach
  </ol>

</div>
