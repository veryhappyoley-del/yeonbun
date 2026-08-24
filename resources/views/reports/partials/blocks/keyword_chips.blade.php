{{--
  keyword_chips 블록 — 문단 속 문장에 자연어로 풀어 쓰던 키워드를 태그 칩(pill) UI로
  분리해서 보여줍니다. "27,000원인데 그냥 글만 있다"는 지적에 대응하는 시각 강화
  블록 중 하나로, 리포트를 한 문장으로 압축하는 챕터(love_profile/final_verdict)에서
  씁니다. ChapterSpec::$schema는 반드시 아래 모양을 따라야 합니다:

    { "keywords": ["키워드1", "키워드2", "..."] }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다.
--}}
@php
  $keywords = is_array($content['keywords'] ?? null) ? $content['keywords'] : [];
  $keywords = array_values(array_filter($keywords, fn ($k) => is_scalar($k) && $k !== ''));
  $chipVariants = ['seal', 'indigo', 'gold'];
@endphp
@if (!empty($keywords))
  <div class="rpt-chip-row">
    @foreach ($keywords as $i => $keyword)
      <span class="rpt-chip rpt-chip--{{ $chipVariants[$i % 3] }}">#{{ $keyword }}</span>
    @endforeach
  </div>
@endif
