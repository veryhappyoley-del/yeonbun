{{--
  챕터형(schema_version=2) 리포트 본문. chapter-toc.blade.php와 함께 reports.show가
  include합니다(기대 변수는 chapter-toc.blade.php와 동일: $report, $type).

  각 챕터는 ChapterSpec::$blocks에 선언된 블록 이름 순서대로
  resources/views/reports/partials/blocks/{block}.blade.php를 include해서 렌더링합니다 —
  새 리포트 타입을 추가해도 이미 있는 블록을 조합하기만 하면 새 Blade 파일이 필요
  없는 게 이 구조의 핵심입니다(블록별로 기대하는 JSON 모양은 각 블록 파일 상단 주석 참고,
  ChapterSpec::$schema를 쓸 때 반드시 그 모양을 따라야 함).

  pending/generating/failed 챕터는 본문 대신 상태 안내 + (failed인 경우) 개별 재시도
  버튼을 보여줍니다. 재시도 버튼 동작은 public/js/report-toc.js가 처리합니다.
--}}
@php
  $rows = $report->chapters->keyBy('chapter_key');
@endphp

<div class="rpt" id="chapter-reader">
  @foreach ($type->chapters as $chapter)
    @php $row = $rows->get($chapter->key); @endphp
    <section id="chapter-{{ $chapter->key }}" class="rpt-section chapter-section" data-chapter-section="{{ $chapter->key }}">
      <div class="rpt-section-title">{{ $chapter->title }}</div>

      @if ($row && $row->isReady())
        @foreach ($chapter->blocks as $block)
          @include('reports.partials.blocks.'.$block, ['content' => $row->content])
        @endforeach
      @elseif ($row && $row->status === 'failed')
        <div class="chapter-pending-note">
          이 챕터는 생성에 실패했어요. 결제는 정상 처리됐으니 안심하고 다시 시도해 주세요.
          <button type="button" class="btn outline chapter-retry-btn"
                  data-chapter-regenerate="{{ route('reports.chapters.regenerate', [$report, $chapter->key]) }}"
                  data-chapter-key="{{ $chapter->key }}">다시 생성하기</button>
        </div>
      @else
        <div class="chapter-pending-note">이 챕터는 아직 준비 중이에요. 잠시 후 새로고침하면 보여요.</div>
      @endif
    </section>
  @endforeach
</div>
