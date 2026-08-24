{{--
  concern_answer 블록 — 궁합 폼에서 사용자가 선택한 "지금 가장 궁금한 것"(primaryConcern)과
  직접 입력한 자유 텍스트(concernDetail)에 대한 직접적인 답을 강조해서 보여주는 카드.

  (2026-08-24 추가) 지금까지는 관련 챕터(개요/장기전망/위기순간/최종결론) 프롬프트에
  "지나가듯 언급하며 답하라"는 지침만 있어서, 정작 그 답이 문단 속에 묻혀 있어 "내가 물어본
  것에 확실히 답을 받았다"는 느낌이 약했다. 이 블록은 리포트 첫 챕터(compat_overview) 맨
  위에 사용자 자신의 질문을 그대로(있는 그대로의 표현으로) 보여주고, 그 바로 아래 AI가 쓴
  직접적인 답을 붙여서 "여기 답 있어요"를 명확하게 한다.

  기대 변수: $content(이 챕터의 report_chapters.content, 반드시 concern_answer 문자열
  키 필요) + $report(App\Models\Report 인스턴스 — chapter-reader.blade.php의 @include는
  Blade 기본 동작상 호출 시점의 전체 변수 스코프를 그대로 넘기므로, 별도로 전달하지 않아도
  $report->input에 그대로 접근 가능). $report->input에는 reports.js의
  buildTwoPersonInput()이 보낸 primaryConcern/concernDetail(둘 다 선택 사항이라 null일
  수 있음)이 들어있다.

  primaryConcern/concernDetail이 둘 다 없으면(사용자가 아예 선택하지 않은 경우) 질문을
  지어내지 않고 이 블록 자체를 렌더링하지 않는다. concern_answer가 비어 있어도 마찬가지.
--}}
@php
  $concernLabels = [
    'continuity' => '지속 가능성 — 잘 맞는지, 이대로 이어질 수 있는지',
    'growth' => '관계 발전 — 연애·결혼 등 다음 단계로 갈 수 있을지',
    'flow' => '앞으로의 흐름 — 가까워질 시기·멀어질 시기가 궁금할 때',
    'friction' => '충돌 완화 — 싸움·오해·마찰이 반복되는 이유',
  ];
  $input = (isset($report) && is_array($report->input ?? null)) ? $report->input : [];
  $rawConcern = trim((string) ($input['concernDetail'] ?? ''));
  $primaryConcern = $input['primaryConcern'] ?? null;
  $question = $rawConcern !== '' ? $rawConcern : ($concernLabels[$primaryConcern] ?? null);
  $answer = trim((string) ($content['concern_answer'] ?? ''));
@endphp
@if ($question && $answer !== '')
  <div class="rpt-concern-answer">
    <div class="rpt-concern-answer-label">🔍 회원님이 가장 궁금해하신 것</div>
    <div class="rpt-concern-answer-question">{{ $question }}</div>
    <div class="rpt-concern-answer-body">{{ $answer }}</div>
  </div>
@endif
