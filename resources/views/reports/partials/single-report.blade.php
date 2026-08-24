{{--
  "심층 연애 리포트"(type=single) 본문. ReportController::show()가 content(JSON)를
  미리 배열로 디코딩해서 $data로 넘겨준다. 스키마는 ReportGenerator::singlePrompt()의
  $schema와 1:1로 대응한다 — 프롬프트를 바꾸면 이 파일의 data_get 경로도 함께 확인할 것.

  구조: 사주 데이터 → 명리학적 특징 → (있다면) 캐릭터 연결 → LOVE PROFILE → LOVE SCORE →
  LOVE OS → WHO ATTRACTS YOU → STRENGTH/WEAKNESS → RECURRING PATTERN →
  PARTNER'S VIEW → COMPATIBILITY → RELATIONSHIP ADVICE → FINAL VERDICT

  (분량 축소로 스키마가 바뀐 이력) 예전엔 LOVE OS(6단계)/PARTNER'S VIEW(5단계)/
  LOVE SIGNAL(별도 5개 섹션)이 따로 있었는데, 셋 다 사실상 같은 "연애 단계별 흐름"을
  반복하고 있어서 LOVE OS·PARTNER'S VIEW를 동일한 4단계(attraction/relationship_start/
  relationship_stage/conflict_crisis)로 합치고, LOVE SIGNAL은 LOVE OS 각 단계의
  signal 줄로 흡수했다. 별도 LOVE SIGNAL 섹션은 더 이상 없음.
--}}
@php
  $pillars = $input['pillars'] ?? [];
  $confidence = $data['confidence'] ?? null;
  $confidenceLabel = ['high' => '높음', 'medium' => '보통', 'low' => '참고용'][$confidence] ?? null;
  $strength = data_get($input, 'deep.dayMasterStrength.label');
@endphp

<div class="rpt">

  {{-- 사주 데이터 --}}
  <div class="rpt-section">
    <div class="rpt-section-title">사주 데이터</div>
    <div class="myeongsik">
      @foreach ([['년주','year'],['월주','month'],['일주','day'],['시주','hour']] as [$roleLabel, $key])
        @php $p = $pillars[$key] ?? null; @endphp
        <div class="pillar">
          <div class="role">{{ $roleLabel }}</div>
          @if ($p)
            <div class="hanja"><span class="stem-el el-{{ $p['stemElement'] ?? '' }}">{{ $p['stemHanja'] ?? '' }}</span><span class="branch-el el-{{ $p['branchElement'] ?? '' }}">{{ $p['branchHanja'] ?? '' }}</span></div>
            <div class="hangul">{{ $p['label'] ?? '' }}</div>
          @else
            <div class="hangul">모름</div>
          @endif
        </div>
      @endforeach
    </div>
    <div class="badge-row" style="margin-top:10px;">
      @if ($strength)
        <span class="badge seal">일간 강약 · {{ $strength }}</span>
      @endif
      @if ($confidenceLabel)
        <span class="badge">분석 신뢰도 · {{ $confidenceLabel }}</span>
      @endif
    </div>
  </div>

  {{-- 명리학적 특징 --}}
  @if (!empty($data['saju_basis']))
    <div class="rpt-section">
      <div class="rpt-section-title">명리학적 특징</div>
      @foreach (['pillars_note', 'day_master_note', 'elemental_note'] as $k)
        @if (!empty($data['saju_basis'][$k]))
          <p class="rpt-p">{{ $data['saju_basis'][$k] }}</p>
        @endif
      @endforeach
    </div>
  @endif

  {{-- 캐릭터 연결(무료 카드와의 연결) --}}
  @if (!empty($data['character_link']))
    @php $cl = $data['character_link']; @endphp
    <div class="rpt-section rpt-character-link">
      <div class="rpt-section-title">이 유형은 왜 이렇게 나왔을까</div>
      @if (!empty($cl['cause']))<p class="rpt-p"><strong>사주적 원인 ·</strong> {{ $cl['cause'] }}</p>@endif
      @if (!empty($cl['behavior']))<p class="rpt-p"><strong>실제 행동 ·</strong> {{ $cl['behavior'] }}</p>@endif
      @if (!empty($cl['trigger_situation']))<p class="rpt-p"><strong>강해지는 상황 ·</strong> {{ $cl['trigger_situation'] }}</p>@endif
      @if (!empty($cl['when_strength']))<p class="rpt-p"><strong>장점으로 작용하면 ·</strong> {{ $cl['when_strength'] }}</p>@endif
      @if (!empty($cl['when_overdone']))<p class="rpt-p"><strong>과해지면 ·</strong> {{ $cl['when_overdone'] }}</p>@endif
    </div>
  @endif

  {{-- LOVE PROFILE --}}
  @if (!empty($data['love_profile']))
    <div class="rpt-section">
      <div class="rpt-section-title">LOVE PROFILE · 나는 어떤 연애 타입인가</div>
      @if (!empty($data['love_profile']['type_keywords']))
        <div class="badge-row">
          @foreach ($data['love_profile']['type_keywords'] as $kw)
            <span class="badge indigo">#{{ $kw }}</span>
          @endforeach
        </div>
      @endif
      @if (!empty($data['love_profile']['summary']))
        <div class="rpt-quote">{{ $data['love_profile']['summary'] }}</div>
      @endif
    </div>
  @endif

  {{-- LOVE SCORE --}}
  @php
    $scoreLabels = [
      'attraction_expression' => '호감 표현', 'relationship_leadership' => '관계 주도력', 'devotion' => '몰입도',
      'emotional_expression' => '감정 표현', 'patience' => '인내심', 'relationship_cutoff' => '관계 정리력',
      'jealousy' => '질투심', 'push_pull_tolerance' => '밀당 내성', 'relationship_stability' => '관계 안정감',
      'conflict_resolution' => '갈등 해결력',
    ];
    $scores = $data['love_score'] ?? [];
  @endphp
  @if ($scores)
    <div class="rpt-section">
      <div class="rpt-section-title">LOVE SCORE · 연애 콘텐츠 지표</div>
      <p class="hint" style="margin-top:0;">사주 해석을 바탕으로 산출한 재미용 콘텐츠 지표예요. 실제 심리검사 결과가 아니에요.</p>
      <div class="rpt-score-list">
        @foreach ($scoreLabels as $key => $label)
          @if (isset($scores[$key]))
            <div class="rpt-score-row">
              <span class="rpt-score-label">{{ $label }}</span>
              <div class="rpt-score-track"><div class="rpt-score-fill" style="width:{{ (int) $scores[$key] }}%"></div></div>
              <span class="rpt-score-value">{{ (int) $scores[$key] }}</span>
            </div>
          @endif
        @endforeach
      </div>
      @if (!empty($scores['note']))
        <p class="rpt-p" style="margin-top:10px;">{{ $scores['note'] }}</p>
      @endif
    </div>
  @endif

  {{-- LOVE OS (예전 LOVE SIGNAL 섹션이 각 단계의 signal 줄로 흡수됨) --}}
  @php
    $osLabels = [
      'attraction' => '① 호감이 생기고 커질 때',
      'relationship_start' => '② 관계가 시작될 때',
      'relationship_stage' => '③ 관계를 이어갈 때',
      'conflict_crisis' => '④ 갈등·위기가 올 때',
    ];
    $os = $data['love_os'] ?? [];
  @endphp
  @if ($os)
    <div class="rpt-section">
      <div class="rpt-section-title">LOVE OS · 나는 연애를 어떻게 운영하는가</div>
      <div class="rpt-os-grid">
        @foreach ($osLabels as $key => $label)
          @php $stage = $os[$key] ?? null; @endphp
          @if ($stage)
            <div class="rpt-os-card">
              <div class="rpt-os-title">{{ $label }}</div>
              @if (!empty($stage['emotion']))<div class="rpt-os-line"><span>감정</span>{{ $stage['emotion'] }}</div>@endif
              @if (!empty($stage['thought']))<div class="rpt-os-line"><span>생각</span>{{ $stage['thought'] }}</div>@endif
              @if (!empty($stage['behavior']))<div class="rpt-os-line"><span>행동</span>{{ $stage['behavior'] }}</div>@endif
              @if (!empty($stage['signal']))<div class="rpt-os-line"><span>신호</span>{{ $stage['signal'] }}</div>@endif
            </div>
          @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- WHO ATTRACTS YOU --}}
  @php
    $attractLabels = [
      'strongly_attracted' => '강하게 끌리는 사람', 'short_term_attraction' => '처음엔 강하지만 오래가긴 어려운 사람',
      'long_term_match' => '장기적으로 잘 맞는 사람',
    ];
    $attracts = $data['who_attracts_you'] ?? [];
  @endphp
  @if ($attracts)
    <div class="rpt-section">
      <div class="rpt-section-title">WHO ATTRACTS YOU · 나는 어떤 사람에게 끌리는가</div>
      <div class="rpt-attract-grid">
        @foreach ($attractLabels as $key => $label)
          @php $a = $attracts[$key] ?? null; @endphp
          @if ($a)
            <div class="rpt-attract-card">
              <div class="rpt-os-title">{{ $label }}</div>
              @if (!empty($a['description']))<p class="rpt-p">{{ $a['description'] }}</p>@endif
              @if (!empty($a['traits']))
                <div class="badge-row">
                  @foreach ($a['traits'] as $t)<span class="badge">{{ $t }}</span>@endforeach
                </div>
              @endif
            </div>
          @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- STRENGTH / WEAKNESS --}}
  @if (!empty($data['strength_weakness']))
    <div class="rpt-section">
      <div class="rpt-section-title">내 연애의 무기와 위험요소</div>
      @foreach ($data['strength_weakness'] as $sw)
        <div class="rpt-sw-card">
          @if (!empty($sw['strength']))<div class="rpt-sw-line rpt-sw-strength"><span>강점</span>{{ $sw['strength'] }}</div>@endif
          @if (!empty($sw['escalation']))<div class="rpt-sw-line rpt-sw-escalation"><span>과도해지면</span>{{ $sw['escalation'] }}</div>@endif
          @if (!empty($sw['weakness']))<div class="rpt-sw-line rpt-sw-weakness"><span>약점</span>{{ $sw['weakness'] }}</div>@endif
        </div>
      @endforeach
    </div>
  @endif

  {{-- RECURRING PATTERN --}}
  @if (!empty($data['recurring_pattern']['steps']))
    <div class="rpt-section">
      <div class="rpt-section-title">반복되기 쉬운 연애 패턴</div>
      <div class="rpt-pattern-flow">
        @foreach ($data['recurring_pattern']['steps'] as $i => $step)
          <div class="rpt-pattern-step"><span class="rpt-pattern-num">{{ $i + 1 }}</span>{{ $step }}</div>
        @endforeach
      </div>
      @if (!empty($data['recurring_pattern']['key_point']))
        <div class="rpt-quote">💡 {{ $data['recurring_pattern']['key_point'] }}</div>
      @endif
    </div>
  @endif

  {{-- PARTNER'S VIEW (LOVE OS와 동일한 4단계 키를 씀 — 나의 내면 vs 상대가 보는 나) --}}
  @php
    $partnerLabels = [
      'attraction' => '① 처음 만났을 때', 'relationship_start' => '② 관계가 시작됐을 때',
      'relationship_stage' => '③ 관계가 깊어질 때', 'conflict_crisis' => '④ 갈등·위기가 왔을 때',
    ];
    $partner = $data['partner_view'] ?? [];
  @endphp
  @if ($partner)
    <div class="rpt-section">
      <div class="rpt-section-title">상대방이 느끼는 내 모습</div>
      <div class="rpt-partner-grid">
        @foreach ($partnerLabels as $key => $label)
          @php $pv = $partner[$key] ?? null; @endphp
          @if ($pv)
            <div class="rpt-partner-card">
              <div class="rpt-os-title">{{ $label }}</div>
              @if (!empty($pv['outside']))<div class="rpt-os-line"><span>겉으로는</span>{{ $pv['outside'] }}</div>@endif
              @if (!empty($pv['inside']))<div class="rpt-os-line"><span>내면에서는</span>{{ $pv['inside'] }}</div>@endif
            </div>
          @endif
        @endforeach
      </div>
    </div>
  @endif

  {{-- COMPATIBILITY --}}
  @php
    $compatLabels = [
      'independence' => '독립성', 'emotional_expression' => '감정 표현', 'realism' => '현실 감각',
      'responsibility' => '책임감', 'stability' => '안정 추구', 'relationship_leadership' => '관계 주도력',
      'dependency' => '의존도', 'emotional_volatility' => '감정 기복', 'communication' => '소통력',
      'lifestyle_compatibility' => '생활 방식',
    ];
    $compat = $data['compatibility'] ?? [];
  @endphp
  @if ($compat)
    <div class="rpt-section">
      <div class="rpt-section-title">COMPATIBILITY · 어떤 사람과 안정적일까</div>
      @if (!empty($compat['scores']))
        <div class="rpt-score-list">
          @foreach ($compatLabels as $key => $label)
            @if (isset($compat['scores'][$key]))
              <div class="rpt-score-row">
                <span class="rpt-score-label">{{ $label }}</span>
                <div class="rpt-score-track"><div class="rpt-score-fill rpt-score-fill--indigo" style="width:{{ (int) $compat['scores'][$key] }}%"></div></div>
                <span class="rpt-score-value">{{ (int) $compat['scores'][$key] }}</span>
              </div>
            @endif
          @endforeach
        </div>
      @endif
      @if (!empty($compat['best_match']))<p class="rpt-p"><strong>잘 맞는 상대 ·</strong> {{ $compat['best_match'] }}</p>@endif
      @if (!empty($compat['caution_match']))<p class="rpt-p"><strong>조심할 상대 ·</strong> {{ $compat['caution_match'] }}</p>@endif
      @if (!empty($compat['ideal_relationship']))<p class="rpt-p"><strong>이상적인 관계 ·</strong> {{ $compat['ideal_relationship'] }}</p>@endif
    </div>
  @endif

  {{-- RELATIONSHIP ADVICE --}}
  @if (!empty($data['relationship_advice']))
    <div class="rpt-section">
      <div class="rpt-section-title">실전 조언</div>
      @foreach ($data['relationship_advice'] as $i => $adv)
        <div class="rpt-advice-card">
          <div class="rpt-advice-num">조언 {{ $i + 1 }}</div>
          @if (!empty($adv['situation']))<div class="rpt-os-line"><span>상황</span>{{ $adv['situation'] }}</div>@endif
          @if (!empty($adv['problem']))<div class="rpt-os-line"><span>문제</span>{{ $adv['problem'] }}</div>@endif
          @if (!empty($adv['action']))<div class="rpt-os-line"><span>추천 행동</span>{{ $adv['action'] }}</div>@endif
        </div>
      @endforeach
    </div>
  @endif

  {{-- FINAL VERDICT --}}
  @if (!empty($data['final_verdict']))
    @php $fv = $data['final_verdict']; @endphp
    <div class="rpt-section rpt-final">
      <div class="rpt-section-title">개인화된 최종 결론</div>
      @if (!empty($fv['statement']))<p class="rpt-p"><strong>{{ $fv['statement'] }}</strong></p>@endif
      @if (!empty($fv['love_keywords']))
        <div class="badge-row">
          @foreach ($fv['love_keywords'] as $kw)<span class="badge seal">#{{ $kw }}</span>@endforeach
        </div>
      @endif
      @if (!empty($fv['closing_line']))
        <div class="rpt-quote rpt-quote--final">{{ $fv['closing_line'] }}</div>
      @endif
    </div>
  @endif

</div>
