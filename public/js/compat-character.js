/**
 * "궁합 유형 카드" — 무료 궁합 결과에 붙는 게임 카드 스타일 콘텐츠 레이어.
 *
 * love-character.js(나의 연애 사주 쪽 "연애 캐릭터 카드")와 정확히 같은 목적/같은 DOM
 * 클래스(.lc-*, public/css/app.css)를 그대로 재사용합니다 — 새 CSS를 만들지 않고,
 * 이미 검증된 시각 언어(오행 테마 그라디언트, 스탯 바, 스킬/약점 패널)를 궁합 데이터로만
 * 갈아끼웁니다. 룰 기반이라(AI 호출 없음) 비용이 전혀 들지 않고, 무료 화면에서도 계산
 * 즉시 바로 보여줄 수 있습니다.
 *
 * app.js의 calcCompat() 결과(score/levelLabel/rel)만 있으면 만들 수 있어서, elementRelation()
 * 4가지(generate/same/control/neutral) × 점수 구간 3단계(high/mid/low) = 12가지 유형으로
 * 분류합니다. app.js가 renderCompatResult()에서 window.YeonbunCompatType.buildCard(state)를
 * 호출해서 카드 DOM을 받아 붙입니다.
 */
(function () {
  'use strict';

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    attrs = attrs || {};
    Object.keys(attrs).forEach(function (k) { node.setAttribute(k, attrs[k]); });
    (children || []).forEach(function (c) { node.appendChild(c); });
    return node;
  }

  function txt(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = text;
    return node;
  }

  // 오행 상생/비화/상극/중립 관계별 테마 — love-character.js의 ELEMENT_THEME과 같은 방식
  // (2026-08-24 수정)으로 사이트 전역 색상 변수를 그대로 참조한다: 상생은 "돕는" 관계라
  // 오행의 목(성장)과 같은 결의 --wood, 비화(같은 오행)는 사이트의 시그니처 색인 --seal,
  // 상극은 부딪히는 긴장감이라 --fire, 중립은 사이트의 세 번째 보조색 --indigo. 예전처럼
  // 카드마다 따로 진한 네온 그라디언트를 하드코딩하지 않아서 사이트 나머지와 톤이 맞고,
  // 라이트/다크 테마 전환에도 자동으로 따라간다.
  var REL_THEME = {
    generate: { icon: '🌊', label: '오행 상생 관계', accent: 'var(--wood)' },
    same: { icon: '🪞', label: '오행 비화 관계', accent: 'var(--seal)' },
    control: { icon: '⚡', label: '오행 상극 관계', accent: 'var(--fire)' },
    neutral: { icon: '🧭', label: '오행 중립 관계', accent: 'var(--indigo)' }
  };

  // rel × band(high/mid/low) = 12가지 궁합 유형. self 캐릭터 카드와 톤을 맞추되(짧고 재미있는
  // 카피), "재미로 보는 콘텐츠"라는 걸 늘 명확히 알 수 있게 과도하게 단정적인 문구는 피함.
  var COMPAT_TYPES = {
    generate_high: {
      typeName: '천생연분 케미형', emoji: '💞',
      oneLiner: ['서로가 서로를 자연스럽게 밀어주는 사이.', '가만히 있어도 손발이 척척 맞는 편이에요.'],
      signatureStat: { label: '케미력', value: 94 },
      stats: [{ label: '안정감', value: 86 }, { label: '설렘', value: 74 }, { label: '성장성', value: 88 }],
      trait: { title: '상생 파트너', desc: '한쪽의 기운이 다른 쪽을 자연스럽게 북돋아주는 관계라, 같이 있을수록 서로가 더 잘 풀리는 편이에요.' },
      points: [
        { name: '포인트 01 · 자연스러운 균형', desc: '애써 맞추지 않아도 흐름이 알아서 맞아떨어져요' },
        { name: '포인트 02 · 시너지', desc: '혼자일 때보다 같이 있을 때 더 좋은 결과가 나오는 편이에요' }
      ],
      caution: { title: '익숙함 주의', desc: '너무 편해서 관계에 소홀해지지 않게, 가끔은 의식적으로 서로를 챙겨보세요' },
      memeTags: ['상생 케미', '자동 밸런스', '함께라서 시너지'],
      rarity: 5
    },
    generate_mid: {
      typeName: '포근한 동행형', emoji: '🌿',
      oneLiner: ['화려하진 않아도 꾸준히 서로를 챙기는 사이.', '천천히, 그래도 확실하게 가까워지는 편이에요.'],
      signatureStat: { label: '동행력', value: 80 },
      stats: [{ label: '안정감', value: 78 }, { label: '설렘', value: 55 }, { label: '성장성', value: 72 }],
      trait: { title: '조용한 상생', desc: '큰 이벤트보다 일상에서 서로를 은근히 도와주는 흐름이 더 편안하게 느껴지는 조합이에요.' },
      points: [
        { name: '포인트 01 · 잔잔한 지지', desc: '큰소리 없이도 서로 힘이 되어주는 편이에요' },
        { name: '포인트 02 · 오래가는 편안함', desc: '시간이 지날수록 관계가 더 안정되는 편이에요' }
      ],
      caution: { title: '자극 부족', desc: '너무 잔잔하기만 하면 설렘이 줄 수 있으니, 가끔은 새로운 걸 같이 해보세요' },
      memeTags: ['잔잔 상생', '편안한 페이스', '느리지만 확실'],
      rarity: 4
    },
    generate_low: {
      typeName: '잠재력 폭발형', emoji: '🌱',
      oneLiner: ['기본 궁합은 좋은데 아직 손발이 덜 맞은 사이.', '조금만 맞춰가면 확 좋아질 여지가 있어요.'],
      signatureStat: { label: '잠재력', value: 82 },
      stats: [{ label: '안정감', value: 52 }, { label: '설렘', value: 60 }, { label: '성장성', value: 85 }],
      trait: { title: '아직 다듬는 중', desc: '오행 관계 자체는 서로에게 힘이 되는 상생인데, 다른 요소들이 아직 안 맞춰진 초기 단계예요.' },
      points: [
        { name: '포인트 01 · 좋은 기본기', desc: '기질적으로는 서로에게 도움이 되는 조합이에요' },
        { name: '포인트 02 · 발전 가능성', desc: '맞춰갈수록 궁합이 더 좋아질 여지가 큰 편이에요' }
      ],
      caution: { title: '조급함 주의', desc: '아직 서로 맞춰가는 단계라는 걸 인정하고, 조급하게 결론 내리지 마세요' },
      memeTags: ['성장 중', '가능성 甲', '지금은 적응기'],
      rarity: 3
    },
    same_high: {
      typeName: '쌍둥이 텔레파시형', emoji: '👯',
      oneLiner: ['말 안 해도 무슨 생각하는지 다 알 것 같은 사이.', '취향도 리듬도 신기할 만큼 잘 맞아요.'],
      signatureStat: { label: '텔레파시력', value: 92 },
      stats: [{ label: '안정감', value: 80 }, { label: '설렘', value: 65 }, { label: '성장성', value: 68 }],
      trait: { title: '평행이론 커플', desc: '취향, 속도, 리듬이 닮아서 대화가 술술 풀리는 편이에요. 닮은 만큼 이해가 빠른 조합이에요.' },
      points: [
        { name: '포인트 01 · 빠른 이해', desc: '설명 안 해도 서로의 의도를 잘 캐치하는 편이에요' },
        { name: '포인트 02 · 공통 취향', desc: '같이 좋아하는 것도, 같이 싫어하는 것도 많은 편이에요' }
      ],
      caution: { title: '닮은 지점 충돌', desc: '너무 닮아서 같은 지점에서 부딪힐 수 있으니, 다름을 인정하는 대화가 도움이 돼요' },
      memeTags: ['평행이론', '텔레파시 통함', '닮은꼴'],
      rarity: 4
    },
    same_mid: {
      typeName: '닮은꼴 룸메이트형', emoji: '🪞',
      oneLiner: ['생활 패턴이 비슷해서 편안한 사이.', '가족 같은 편안함이 매력이에요.'],
      signatureStat: { label: '편안력', value: 78 },
      stats: [{ label: '안정감', value: 74 }, { label: '설렘', value: 48 }, { label: '성장성', value: 60 }],
      trait: { title: '동거인 케미', desc: '연인이라기보단 오래된 룸메이트처럼 편안한 조합이에요. 안정적이지만 가끔은 심심할 수 있어요.' },
      points: [
        { name: '포인트 01 · 생활 궁합', desc: '일상 리듬이 비슷해서 같이 지내기 편한 편이에요' },
        { name: '포인트 02 · 편안한 대화', desc: '눈치 안 보고 편하게 이야기할 수 있는 사이예요' }
      ],
      caution: { title: '설렘 관리', desc: '편안함에 익숙해져서 설렘이 사라지지 않게, 의식적으로 이벤트를 만들어보세요' },
      memeTags: ['가족 같은 편안함', '동거인 케미', '설렘 관리 필요'],
      rarity: 3
    },
    same_low: {
      typeName: '동족상잔 주의형', emoji: '⚔️',
      oneLiner: ['너무 닮아서 오히려 자주 부딪히는 사이.', '거울 보듯 서로의 단점이 잘 보여요.'],
      signatureStat: { label: '자극지수', value: 75 },
      stats: [{ label: '안정감', value: 40 }, { label: '설렘', value: 58 }, { label: '성장성', value: 65 }],
      trait: { title: '거울 관계', desc: '같은 성향이 부딪히면 크게 느껴질 수 있는 조합이에요. 서로가 서로의 단점을 비추는 거울 같은 사이예요.' },
      points: [
        { name: '포인트 01 · 빠른 공감', desc: '비슷한 성향이라 서로의 감정을 빨리 이해하는 편이에요' },
        { name: '포인트 02 · 자기 성찰', desc: '상대를 통해 자기 모습을 돌아보게 되는 계기가 많아요' }
      ],
      caution: { title: '같은 지점 충돌', desc: '같은 성향끼리 부딪히면 감정이 커질 수 있으니, 한 박자 쉬고 대화하는 습관이 필요해요' },
      memeTags: ['거울 커플', '닮아서 부딪힘', '자기 성찰 유발'],
      rarity: 3
    },
    control_high: {
      typeName: '밀당 스파크형', emoji: '⚡',
      oneLiner: ['부딪히는 듯한데 이상하게 자꾸 끌리는 사이.', '긴장감이 오히려 매력 포인트예요.'],
      signatureStat: { label: '스파크력', value: 88 },
      stats: [{ label: '안정감', value: 62 }, { label: '설렘', value: 90 }, { label: '성장성', value: 70 }],
      trait: { title: '자석 같은 긴장감', desc: '오행이 서로를 극하는 관계지만, 다른 요소들이 잘 받쳐줘서 그 긴장감이 오히려 매력으로 작용하는 조합이에요.' },
      points: [
        { name: '포인트 01 · 강렬한 끌림', desc: '초반부터 서로에게 확 끌리는 편이에요' },
        { name: '포인트 02 · 자극이 되는 관계', desc: '적당한 긴장감이 관계를 더 흥미롭게 만들어요' }
      ],
      caution: { title: '자존심 대결', desc: '자기주장이 부딪히기 쉬운 궁합이라, 의식적으로 배려하는 노력이 관계를 더 단단하게 만들어요' },
      memeTags: ['밀당 만렙', '긴장감이 매력', '자석 케미'],
      rarity: 5
    },
    control_mid: {
      typeName: '롤러코스터 케미형', emoji: '🎢',
      oneLiner: ['좋을 땐 정말 좋고, 부딪힐 땐 확실히 부딪히는 사이.', '기복이 있지만 그만큼 역동적이에요.'],
      signatureStat: { label: '역동성', value: 76 },
      stats: [{ label: '안정감', value: 48 }, { label: '설렘', value: 72 }, { label: '성장성', value: 66 }],
      trait: { title: '기복형 케미', desc: '오행이 서로를 극하는 관계라 감정 기복이 있는 편이에요. 안정보다는 역동적인 흐름에 가까운 조합이에요.' },
      points: [
        { name: '포인트 01 · 지루하지 않음', desc: '관계가 늘 잔잔하지만은 않아서 자극이 되는 편이에요' },
        { name: '포인트 02 · 화해 후 더 가까워짐', desc: '갈등을 넘기고 나면 오히려 사이가 더 돈독해지곤 해요' }
      ],
      caution: { title: '감정 기복 관리', desc: '싸움이 크게 느껴질 수 있으니, 감정이 격해지기 전에 잠깐 거리를 두는 연습이 도움이 돼요' },
      memeTags: ['롤러코스터', '기복 있는 케미', '화해가 관건'],
      rarity: 3
    },
    control_low: {
      typeName: '정면충돌 주의형', emoji: '💥',
      oneLiner: ['자기주장이 강한 두 사람이 만난 사이.', '의식적인 배려가 특히 중요해요.'],
      signatureStat: { label: '충돌지수', value: 80 },
      stats: [{ label: '안정감', value: 32 }, { label: '설렘', value: 68 }, { label: '성장성', value: 55 }],
      trait: { title: '강 대 강 조합', desc: '오행이 서로를 극하는 데다 다른 요소들도 아직 안 맞춰진 초반 단계예요. 서로에게 맞추려는 의식적인 노력이 특히 중요해요.' },
      points: [
        { name: '포인트 01 · 솔직한 관계', desc: '서로 할 말은 하는 편이라 관계가 명확한 편이에요' },
        { name: '포인트 02 · 성장의 계기', desc: '부딪히는 만큼 서로에게 배우는 것도 많은 편이에요' }
      ],
      caution: { title: '배려 필수', desc: '자기주장을 잠깐 내려놓고 상대 입장에서 한 번 더 생각해보는 습관이 관계를 지켜줘요' },
      memeTags: ['강대강', '배려가 관건', '성장형 갈등'],
      rarity: 2
    },
    neutral_high: {
      typeName: '느슨한 자유연애형', emoji: '🎈',
      oneLiner: ['서로를 얽매지 않으면서도 잘 맞는 사이.', '자유로운 분위기에서 편안함을 느껴요.'],
      signatureStat: { label: '자유도', value: 85 },
      stats: [{ label: '안정감', value: 70 }, { label: '설렘', value: 62 }, { label: '성장성', value: 74 }],
      trait: { title: '느슨한 밸런스', desc: '오행상 직접적인 생·극 관계는 아니지만, 다른 요소들이 잘 받쳐줘서 편안하게 잘 맞는 조합이에요.' },
      points: [
        { name: '포인트 01 · 부담 없는 관계', desc: '서로를 얽매지 않아서 관계가 편안한 편이에요' },
        { name: '포인트 02 · 무난한 조화', desc: '큰 갈등 없이 자연스럽게 맞춰가는 편이에요' }
      ],
      caution: { title: '거리감 주의', desc: '너무 느슨하면 서로에게 소홀해질 수 있으니, 가끔은 확실하게 마음을 표현해보세요' },
      memeTags: ['자유로운 케미', '부담 없음', '느슨하지만 편안'],
      rarity: 4
    },
    neutral_mid: {
      typeName: '무난한 페이스메이커형', emoji: '🚶',
      oneLiner: ['크게 튀지 않고 무난하게 맞춰가는 사이.', '서로의 속도를 존중하는 편이에요.'],
      signatureStat: { label: '보폭력', value: 70 },
      stats: [{ label: '안정감', value: 62 }, { label: '설렘', value: 50 }, { label: '성장성', value: 62 }],
      trait: { title: '무난 조합', desc: '오행상 직접적인 관계는 아니라, 좋고 나쁨보다는 서로 맞춰가는 흐름에 가까운 조합이에요.' },
      points: [
        { name: '포인트 01 · 무난한 시작', desc: '초반 부담이 적어서 편하게 시작할 수 있는 편이에요' },
        { name: '포인트 02 · 맞춰가는 재미', desc: '서로를 알아가면서 궁합을 만들어가는 재미가 있어요' }
      ],
      caution: { title: '적극성 필요', desc: '무난한 만큼 서로에게 관심을 적극적으로 표현하지 않으면 심심해질 수 있어요' },
      memeTags: ['무난 그 자체', '맞춰가는 중', '페이스메이커'],
      rarity: 3
    },
    neutral_low: {
      typeName: '각자의길 존중형', emoji: '🧭',
      oneLiner: ['아직은 서로의 속도가 다르게 느껴지는 사이.', '이해와 존중이 특히 중요해요.'],
      signatureStat: { label: '존중력', value: 68 },
      stats: [{ label: '안정감', value: 45 }, { label: '설렘', value: 52 }, { label: '성장성', value: 58 }],
      trait: { title: '탐색 단계', desc: '오행상 직접적인 관계도 아니고 다른 요소들도 아직 안 맞춰진, 서로를 알아가는 초반 단계예요.' },
      points: [
        { name: '포인트 01 · 각자의 개성', desc: '서로 다른 매력을 갖고 있어서 알아가는 재미가 있어요' },
        { name: '포인트 02 · 천천히 알아가기', desc: '급하지 않게 서로를 이해해갈 여지가 있는 편이에요' }
      ],
      caution: { title: '조급함 금지', desc: '아직 서로를 잘 모르는 단계일 수 있으니, 성급하게 맞다/안 맞다를 판단하지 마세요' },
      memeTags: ['탐색기', '천천히', '서로 존중이 중요'],
      rarity: 2
    }
  };

  function scoreBand(score) {
    if (score >= 82) return 'high';
    if (score >= 60) return 'mid';
    return 'low';
  }

  function getCompatType(rel, score) {
    var key = (rel || 'neutral') + '_' + scoreBand(score);
    return COMPAT_TYPES[key] || COMPAT_TYPES.neutral_mid;
  }

  function getTheme(rel) {
    return REL_THEME[rel] || REL_THEME.neutral;
  }

  function starString(rarity) {
    rarity = Math.max(1, Math.min(5, rarity || 3));
    return '★★★★★☆☆☆☆☆'.slice(5 - rarity, 10 - rarity);
  }

  function buildCard(state) {
    var sajuA = state.sajuA, sajuB = state.sajuB, compat = state.compat;
    var nameA = state.nameA || 'A', nameB = state.nameB || 'B';
    var theme = getTheme(compat.rel);
    var type = getCompatType(compat.rel, compat.score);

    var card = el('div', { class: 'lc-card' });
    card.style.setProperty('--lc-accent', theme.accent);

    var topline = el('div', { class: 'lc-topline' });
    topline.appendChild(txt('span', 'lc-kicker', 'COMPAT TYPE CARD'));
    topline.appendChild(el('span', { class: 'lc-rarity', title: '재미로 보는 콘텐츠 등급이에요' }, [document.createTextNode(starString(type.rarity))]));
    card.appendChild(topline);

    card.appendChild(txt('div', 'lc-greet', nameA + ' × ' + nameB + '는'));
    card.appendChild(txt('div', 'lc-type', type.emoji + ' ' + type.typeName));

    var attrRow = el('div', { class: 'lc-attr-row' });
    attrRow.appendChild(txt('span', 'lc-attr-badge', theme.icon + ' ' + theme.label));
    attrRow.appendChild(txt('span', 'lc-attr-badge', '궁합 점수 ' + compat.score + '점'));
    card.appendChild(attrRow);

    var oneliner = el('div', { class: 'lc-oneliner' });
    type.oneLiner.forEach(function (line) { oneliner.appendChild(txt('span', '', line)); });
    card.appendChild(oneliner);

    var tags = el('div', { class: 'lc-tags' });
    type.memeTags.forEach(function (t) { tags.appendChild(txt('span', 'lc-tag', '#' + t)); });
    card.appendChild(tags);

    var statsPanel = el('div', { class: 'lc-panel' });
    statsPanel.appendChild(txt('div', 'lc-panel-title', 'COMPAT STATS'));
    [type.signatureStat].concat(type.stats).forEach(function (s, i) {
      var row = el('div', { class: 'lc-stat-row' + (i === 0 ? ' lc-stat-row--signature' : '') });
      row.appendChild(txt('span', 'lc-stat-label', s.label));
      var track = el('div', { class: 'lc-stat-track' });
      var fill = el('div', { class: 'lc-stat-fill' });
      fill.style.width = s.value + '%';
      track.appendChild(fill);
      row.appendChild(track);
      row.appendChild(txt('span', 'lc-stat-value', String(s.value)));
      statsPanel.appendChild(row);
    });
    card.appendChild(statsPanel);

    var traitPanel = el('div', { class: 'lc-panel' });
    traitPanel.appendChild(txt('div', 'lc-trait-label', '관계 특성'));
    traitPanel.appendChild(txt('div', 'lc-trait-title', type.trait.title));
    traitPanel.appendChild(txt('div', 'lc-trait-desc', type.trait.desc));
    card.appendChild(traitPanel);

    var pointPanel = el('div', { class: 'lc-panel' });
    pointPanel.appendChild(txt('div', 'lc-panel-title', '우리만의 포인트'));
    var grid = el('div', { class: 'lc-skill-grid' });
    type.points.forEach(function (p) {
      var box = el('div', { class: 'lc-skill' });
      box.appendChild(txt('div', 'lc-skill-name', p.name));
      box.appendChild(txt('div', 'lc-skill-desc', p.desc));
      grid.appendChild(box);
    });
    pointPanel.appendChild(grid);
    card.appendChild(pointPanel);

    var cautionPanel = el('div', { class: 'lc-panel lc-weakness' });
    cautionPanel.appendChild(txt('div', 'lc-weakness-label', '⚠️ CAUTION'));
    cautionPanel.appendChild(txt('div', 'lc-weakness-title', type.caution.title));
    cautionPanel.appendChild(txt('div', 'lc-weakness-desc', type.caution.desc));
    card.appendChild(cautionPanel);

    var footer = el('div', { class: 'lc-footer' });
    footer.appendChild(txt('span', '', '결 · 궁합 유형 카드'));
    footer.appendChild(txt('span', '', '※ 재미로 보는 콘텐츠 등급'));
    card.appendChild(footer);

    return card;
  }

  window.YeonbunCompatType = {
    REL_THEME: REL_THEME,
    COMPAT_TYPES: COMPAT_TYPES,
    getTheme: getTheme,
    getCompatType: getCompatType,
    buildCard: buildCard
  };
})();
