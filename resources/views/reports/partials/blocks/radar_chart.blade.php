{{--
  radar_chart 블록 — score_bars와 완전히 같은 데이터 모양(scores/scores_note)을 SVG
  레이더(방사형) 차트로 그립니다. 5~7개 지표를 한눈에 비교할 때 막대 목록보다 훨씬
  "그래프답게" 보이는 시각화라서, 점수형 챕터(표현력/안정감/궁합 지표)를 score_bars
  대신 이 블록으로 렌더링합니다. ChapterSpec::$schema는 score_bars와 완전히 동일합니다
  (스키마/프롬프트 변경 없이 blocks만 바꿔서 재사용 가능):

    { "scores": { "임의_키": { "label": "", "value": 0~100 } }, "scores_note": "" }

  $content는 이 챕터의 report_chapters.content(디코딩된 배열) 전체입니다. 축이 3개
  미만이면(레이더 도형 자체가 의미 없으므로) score_bars 막대 목록으로 자동 대체합니다.
--}}
@php
  $rawScores = is_array($content['scores'] ?? null) ? array_values($content['scores']) : [];
  $scores = array_values(array_filter($rawScores, fn ($s) => is_array($s) && isset($s['label'], $s['value']) && is_scalar($s['label']) && is_numeric($s['value'])));
  $n = count($scores);
@endphp

@if ($n >= 3)
  @php
    $cx = 160;
    $cy = 160;
    $r = 88;

    $angleFor = fn ($i) => (2 * M_PI * $i / $n) - (M_PI / 2);
    $pointAt = function ($i, float $ratio) use ($cx, $cy, $r, $angleFor) {
        $a = $angleFor($i);

        return [$cx + $r * $ratio * cos($a), $cy + $r * $ratio * sin($a)];
    };

    $rings = [];
    foreach ([0.33, 0.66, 1.0] as $ratio) {
        $pts = [];
        for ($i = 0; $i < $n; $i++) {
            [$x, $y] = $pointAt($i, $ratio);
            $pts[] = round($x, 1).','.round($y, 1);
        }
        $rings[] = implode(' ', $pts);
    }

    $axisLines = [];
    $shapePoints = [];
    $dots = [];
    $labels = [];

    foreach ($scores as $i => $s) {
        $value = max(0, min(100, (int) $s['value']));

        [$ex, $ey] = $pointAt($i, 1.0);
        $axisLines[] = [round($ex, 1), round($ey, 1)];

        [$dx, $dy] = $pointAt($i, $value / 100);
        $shapePoints[] = round($dx, 1).','.round($dy, 1);
        $dots[] = [round($dx, 1), round($dy, 1)];

        [$lx, $ly] = $pointAt($i, 1.36);
        $anchor = $lx > $cx + 6 ? 'start' : ($lx < $cx - 6 ? 'end' : 'middle');
        $labels[] = ['x' => round($lx, 1), 'y' => round($ly, 1), 'anchor' => $anchor, 'label' => $s['label'], 'value' => $value];
    }
  @endphp
  <div class="rpt-radar-wrap">
    <svg viewBox="0 0 320 320" class="rpt-radar-svg" role="img" aria-label="점수 레이더 차트">
      @foreach ($rings as $ring)
        <polygon class="rpt-radar-ring" points="{{ $ring }}" />
      @endforeach
      @foreach ($axisLines as $line)
        <line class="rpt-radar-axis" x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $line[0] }}" y2="{{ $line[1] }}" />
      @endforeach
      <polygon class="rpt-radar-shape" points="{{ implode(' ', $shapePoints) }}" />
      @foreach ($dots as $dot)
        <circle class="rpt-radar-dot" cx="{{ $dot[0] }}" cy="{{ $dot[1] }}" r="3.5" />
      @endforeach
      @foreach ($labels as $l)
        <text class="rpt-radar-label" x="{{ $l['x'] }}" y="{{ $l['y'] }}" text-anchor="{{ $l['anchor'] }}">{{ $l['label'] }}</text>
        <text class="rpt-radar-value" x="{{ $l['x'] }}" y="{{ $l['y'] + 15 }}" text-anchor="{{ $l['anchor'] }}">{{ $l['value'] }}</text>
      @endforeach
    </svg>
  </div>
  @if (!empty($content['scores_note']) && is_scalar($content['scores_note']))
    <p class="rpt-p" style="margin-top:10px;">{{ $content['scores_note'] }}</p>
  @endif
@else
  @include('reports.partials.blocks.score_bars', ['content' => $content])
@endif
