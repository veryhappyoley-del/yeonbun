/**
 * "연애 캐릭터 카드" — 나의 연애 사주 결과를 게임 캐릭터 카드처럼 보여주는 모듈.
 *
 * 사주 계산(app.js의 calcSaju/analyzeLove)은 전혀 건드리지 않고, 그 결과(오행 일간 +
 * 음양)를 받아서 순전히 "재미용 콘텐츠 레이어"만 얹는다. 오행×음양 10가지 조합마다
 * 유형명/한줄평/스탯/특성/스킬/약점/밈태그/희귀도를 데이터로 갖고 있어서, 나중에
 * 유형을 다듬거나 새 오행 테마를 추가할 때 이 파일만 건드리면 된다.
 *
 * app.js가 renderSingleResult()에서 window.YeonbunLoveCharacter.buildCard(...)를 호출해서
 * 카드 DOM을 받아 붙인다. reports.js(공유 카드)도 같은 데이터를 참고해서 유형명/한줄평을
 * 통일한다.
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

  // 오행별 테마 — 색상/아이콘/키워드. 금(金)은 요청대로 "단단함/차가움/날카로움/세련됨"을
  // 시각적으로 담은 차갑고 세련된 금속 톤으로 구성. 나머지 4개 오행도 같은 구조로 확장.
  var ELEMENT_THEME = {
    목: {
      icon: '🌱', label: '목(木) 속성',
      gradient: ['#2f6b4f', '#5fae7a'], accent: '#7bd39a', glow: 'rgba(95,174,122,0.45)',
      keywords: ['생동감', '유연함', '성장', '신선함']
    },
    화: {
      icon: '🔥', label: '화(火) 속성',
      gradient: ['#7a2b2b', '#e0603c'], accent: '#ff9166', glow: 'rgba(224,96,60,0.45)',
      keywords: ['열정', '속도감', '뜨거움', '폭발력']
    },
    토: {
      icon: '⛰️', label: '토(土) 속성',
      gradient: ['#6b4d26', '#c99a52'], accent: '#e0b872', glow: 'rgba(185,133,63,0.4)',
      keywords: ['안정감', '묵직함', '포용력', '신뢰']
    },
    금: {
      icon: '⚙️', label: '금(金) 속성',
      gradient: ['#333f4a', '#8fa3b3'], accent: '#cfe0ea', glow: 'rgba(140,163,179,0.5)',
      keywords: ['단단함', '차가움', '날카로움', '세련됨']
    },
    수: {
      icon: '💧', label: '수(水) 속성',
      gradient: ['#1a3252', '#3f6fa8'], accent: '#8fc4ff', glow: 'rgba(63,111,168,0.45)',
      keywords: ['깊이감', '유동성', '신비로움', '고요함']
    }
  };

  // 오행(일간) × 음양 10가지 조합 = 10가지 연애 캐릭터 유형.
  // stats는 항상 [책임감, 표현력, 고집, 밀당] 순서로 고정(카드 레이아웃 일관성용),
  // signatureStat은 유형마다 다른 "시그니처 스탯"(예: 츤데레력) 하나를 맨 위에 별도 강조.
  var LOVE_CHARACTERS = {
    '목_양': {
      typeName: '직진 대시형', emoji: '🌱',
      oneLiner: ['마음 정하면 그날 바로 대시함.', '밀당 따위 모르고 그냥 직진.'],
      signatureStat: { label: '직진력', value: 95 },
      stats: [{ label: '책임감', value: 75 }, { label: '표현력', value: 90 }, { label: '고집', value: 60 }, { label: '밀당', value: 20 }],
      trait: { title: '돌진 본능', desc: '고민보다 행동이 빠른 스타일. 마음이 서면 이미 연락 중.' },
      skills: [
        { name: '즉시 고백', desc: '눈치 안 보고 마음을 바로 던진다' },
        { name: '리드 본능', desc: '데이트 코스부터 분위기까지 알아서 이끈다' }
      ],
      weakness: { title: '급발진', desc: '너무 빨리 다가가서 상대가 부담스러워할 수 있음' },
      memeTags: ['직진 그 자체', '밀당 불가', '오늘도 고백각'],
      rarity: 4
    },
    '목_음': {
      typeName: '은근 성장형', emoji: '🌿',
      oneLiner: ['티는 안 내는데 계속 옆에 있음.', '관심 없어 보여도 은근 다 챙김.'],
      signatureStat: { label: '성장력', value: 85 },
      stats: [{ label: '책임감', value: 80 }, { label: '표현력', value: 45 }, { label: '고집', value: 55 }, { label: '밀당', value: 50 }],
      trait: { title: '조용한 성장충', desc: '연애도 자기계발처럼 천천히, 꾸준히 나아지는 타입.' },
      skills: [
        { name: '은근 챙김', desc: '티 안 내고 필요한 순간에 딱 나타난다' },
        { name: '슬로우 스타터', desc: '천천히 마음을 쌓아 오래가는 관계를 만든다' }
      ],
      weakness: { title: '타이밍 놓침', desc: '고백 타이밍 재다가 남한테 뺏길 수 있음' },
      memeTags: ['은근 다정', '슬로우지만 확실', '고백 타이밍 고민중'],
      rarity: 4
    },
    '화_양': {
      typeName: '텐션 메이커형', emoji: '🔥',
      oneLiner: ['만난 지 3일 만에 이미 최애.', '리액션 하나로 분위기 다 살림.'],
      signatureStat: { label: '텐션력', value: 95 },
      stats: [{ label: '책임감', value: 55 }, { label: '표현력', value: 95 }, { label: '고집', value: 40 }, { label: '밀당', value: 15 }],
      trait: { title: '불꽃 스타터', desc: '초반 텐션이 압도적. 만나자마자 케미가 터진다.' },
      skills: [
        { name: '리액션 폭격', desc: '상대 말 한마디에도 텐션 200% 반응' },
        { name: '급속 친밀', desc: '어색함을 순삭시키는 텐션으로 거리를 좁힌다' }
      ],
      weakness: { title: '금방 식음', desc: '초반 텐션이 유지 안 되면 관심이 빨리 꺼질 수 있음' },
      memeTags: ['텐션 만렙', '리액션 장인', '꾸준함 버프 필요'],
      rarity: 5
    },
    '화_음': {
      typeName: '잔잔한 불꽃형', emoji: '🕯️',
      oneLiner: ['겉은 잔잔한데 속은 계속 타는 중.', '표현은 없어도 마음은 이미 활활.'],
      signatureStat: { label: '내적불꽃력', value: 90 },
      stats: [{ label: '책임감', value: 65 }, { label: '표현력', value: 35 }, { label: '고집', value: 50 }, { label: '밀당', value: 60 }],
      trait: { title: '은은한 열정가', desc: '티는 안 나지만 혼자 설렘 지수 최고치를 찍는 타입.' },
      skills: [
        { name: '조용한 몰입', desc: '티 안 내고 혼자 이미 다 진행됨' },
        { name: '묵묵 서포트', desc: '말은 없어도 챙길 건 다 챙긴다' }
      ],
      weakness: { title: '타이밍 못 잡음', desc: '마음을 너무 오래 숨겨서 상대가 눈치를 못 챌 수 있음' },
      memeTags: ['티 안 나는 폭탄', '혼자 이미 진심', '표현 버프 시급'],
      rarity: 4
    },
    '토_양': {
      typeName: '든든한 베이스형', emoji: '🏡',
      oneLiner: ['말은 직진인데 행동은 진국.', '믿음 하나는 확실하게 줌.'],
      signatureStat: { label: '신뢰력', value: 90 },
      stats: [{ label: '책임감', value: 95 }, { label: '표현력', value: 55 }, { label: '고집', value: 65 }, { label: '밀당', value: 30 }],
      trait: { title: '베이스캠프', desc: '같이 있으면 이상하게 편안하고 안심되는 존재감.' },
      skills: [
        { name: '묵직한 약속', desc: '한번 한 말은 반드시 지킨다' },
        { name: '편안 필드', desc: '함께 있는 것만으로 안정감을 준다' }
      ],
      weakness: { title: '변화 거부감', desc: '익숙한 방식을 벗어나는 걸 유독 힘들어함' },
      memeTags: ['믿음직 그 자체', '변화는 무서워', '장기전 최적화'],
      rarity: 4
    },
    '토_음': {
      typeName: '묵묵한 진심형', emoji: '🌾',
      oneLiner: ['말수는 적은데 마음은 제일 큼.', '확신 서기까진 오래, 서고 나면 평생.'],
      signatureStat: { label: '진심력', value: 95 },
      stats: [{ label: '책임감', value: 90 }, { label: '표현력', value: 25 }, { label: '고집', value: 70 }, { label: '밀당', value: 45 }],
      trait: { title: '묵직한 신뢰파', desc: '표현이 서툴러도 행동으로 진심을 증명하는 타입.' },
      skills: [
        { name: '장기 신뢰', desc: '오래 지켜본 뒤 조용히 곁을 지킨다' },
        { name: '티 안나는 진심', desc: '말 대신 꾸준한 행동으로 마음을 보여준다' }
      ],
      weakness: { title: '확신까지 오래', desc: '마음을 정하는 데까지 시간이 너무 오래 걸릴 수 있음' },
      memeTags: ['말보다 행동', '존버 마스터', '확신 오래 걸림'],
      rarity: 3
    },
    '금_양': {
      typeName: '확신의 아이코닉형', emoji: '✨',
      oneLiner: ['기준 통과하면 그 순간 직진.', '애매한 거 딱 질색, 확실한 게 좋음.'],
      signatureStat: { label: '확신력', value: 90 },
      stats: [{ label: '책임감', value: 85 }, { label: '표현력', value: 60 }, { label: '고집', value: 80 }, { label: '밀당', value: 25 }],
      trait: { title: '명확주의자', desc: '관계에도 뚜렷한 기준이 있고, 통과하면 망설임이 없다.' },
      skills: [
        { name: '기준 통과 알림', desc: '마음에 들면 티가 확실하게 난다' },
        { name: '일편단심 모드', desc: '한번 정한 사람에게는 흔들리지 않는다' }
      ],
      weakness: { title: '기준 미달 컷', desc: '기준에 안 맞으면 가차없이 마음을 접는 편' },
      memeTags: ['기준 확실', '애매한 관계 거부', '한번 정하면 끝까지'],
      rarity: 5
    },
    '금_음': {
      typeName: '츤데레 원칙형', emoji: '❄️',
      oneLiner: ['좋아해도 먼저 표현하진 않음.', '대신 마음먹으면 쉽게 안 놓음.'],
      signatureStat: { label: '츤데레력', value: 100 },
      stats: [{ label: '책임감', value: 90 }, { label: '표현력', value: 40 }, { label: '고집', value: 80 }, { label: '밀당', value: 70 }],
      trait: { title: '쉽게 안 녹음', desc: '겉으로는 차가워 보여도 한번 마음을 주면 오래가는 타입.' },
      skills: [
        { name: '철벽', desc: '호감이 있어도 아무렇지 않은 척한다' },
        { name: '일편단심', desc: '한번 마음먹으면 쉽게 마음을 바꾸지 않는다' }
      ],
      weakness: { title: '감정 표현', desc: '마음은 있는데 말로 표현하는 건 어려움.' },
      memeTags: ['금속성', '쉽게 안 녹음', '호감 있어도 티 안 냄', '기준 미달 = 바로 OUT'],
      rarity: 4
    },
    '수_양': {
      typeName: '센스만렙 눈치형', emoji: '💧',
      oneLiner: ['분위기 파악 끝나면 바로 액션.', '눈치 빠른데 표현도 빠름.'],
      signatureStat: { label: '눈치력', value: 95 },
      stats: [{ label: '책임감', value: 70 }, { label: '표현력', value: 70 }, { label: '고집', value: 45 }, { label: '밀당', value: 55 }],
      trait: { title: '센스 만렙', desc: '상대 기분을 귀신같이 캐치하고 바로 맞춰주는 타입.' },
      skills: [
        { name: '분위기 스캔', desc: '상대 컨디션과 기분을 실시간으로 캐치한다' },
        { name: '타이밍 저격', desc: '딱 필요한 순간에 딱 맞는 말을 건넨다' }
      ],
      weakness: { title: '과잉 배려', desc: '상대 눈치를 너무 봐서 정작 내 마음은 뒷전이 될 수 있음' },
      memeTags: ['눈치 100단', '분위기 메이커', '내 마음은 언제 챙기지'],
      rarity: 4
    },
    '수_음': {
      typeName: '잔잔한 감성형', emoji: '🌊',
      oneLiner: ['속마음은 바다처럼 깊음, 겉은 잔잔.', '말 안 해도 다 느끼고 있음.'],
      signatureStat: { label: '감성력', value: 95 },
      stats: [{ label: '책임감', value: 75 }, { label: '표현력', value: 30 }, { label: '고집', value: 40 }, { label: '밀당', value: 65 }],
      trait: { title: '깊은 물빛', desc: '감정이 섬세하고 깊지만, 겉으로는 잘 드러내지 않는 타입.' },
      skills: [
        { name: '감정 스캔', desc: '말 안 해도 상대 기분 변화를 먼저 알아챈다' },
        { name: '은은한 여운', desc: '화려하지 않아도 오래 기억에 남는 매력을 남긴다' }
      ],
      weakness: { title: '속마음 침묵', desc: '진짜 마음을 잘 안 꺼내서 상대가 답답해할 수 있음' },
      memeTags: ['속은 순정파', '티는 안 남', '여운 甲'],
      rarity: 4
    }
  };

  function getTheme(dayElement) {
    return ELEMENT_THEME[dayElement] || ELEMENT_THEME['금'];
  }

  function getCharacter(dayElement, dayYinYang) {
    return LOVE_CHARACTERS[dayElement + '_' + dayYinYang] || null;
  }

  function starString(rarity) {
    rarity = Math.max(1, Math.min(5, rarity || 4));
    return '★★★★★☆☆☆☆☆'.slice(5 - rarity, 10 - rarity);
  }

  function buildCard(state) {
    var saju = state.saju, love = state.love, name = state.name;
    var theme = getTheme(love.dayEl);
    var character = getCharacter(love.dayEl, love.dayYY);
    if (!character) return el('div', {});

    var card = el('div', { class: 'lc-card' });
    card.style.setProperty('--lc-bg-1', theme.gradient[0]);
    card.style.setProperty('--lc-bg-2', theme.gradient[1]);
    card.style.setProperty('--lc-accent', theme.accent);
    card.style.setProperty('--lc-glow', theme.glow);

    var topline = el('div', { class: 'lc-topline' });
    topline.appendChild(txt('span', 'lc-kicker', 'LOVE TYPE CARD'));
    topline.appendChild(el('span', { class: 'lc-rarity', title: '재미로 보는 콘텐츠 등급이에요' }, [document.createTextNode(starString(character.rarity))]));
    card.appendChild(topline);

    card.appendChild(txt('div', 'lc-greet', (name ? name : '나') + '님은'));
    card.appendChild(txt('div', 'lc-type', character.emoji + ' ' + character.typeName));

    var attrRow = el('div', { class: 'lc-attr-row' });
    attrRow.appendChild(txt('span', 'lc-attr-badge', theme.icon + ' ' + theme.label));
    attrRow.appendChild(txt('span', 'lc-attr-badge', saju.day.label + '(' + saju.day.hanja + ') 일주'));
    card.appendChild(attrRow);

    var oneliner = el('div', { class: 'lc-oneliner' });
    character.oneLiner.forEach(function (line) { oneliner.appendChild(txt('span', '', line)); });
    card.appendChild(oneliner);

    var tags = el('div', { class: 'lc-tags' });
    character.memeTags.forEach(function (t) { tags.appendChild(txt('span', 'lc-tag', '#' + t)); });
    card.appendChild(tags);

    var statsPanel = el('div', { class: 'lc-panel' });
    statsPanel.appendChild(txt('div', 'lc-panel-title', 'LOVE STATS'));
    [character.signatureStat].concat(character.stats).forEach(function (s, i) {
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
    traitPanel.appendChild(txt('div', 'lc-trait-label', '특성'));
    traitPanel.appendChild(txt('div', 'lc-trait-title', character.trait.title));
    traitPanel.appendChild(txt('div', 'lc-trait-desc', character.trait.desc));
    card.appendChild(traitPanel);

    var skillPanel = el('div', { class: 'lc-panel' });
    skillPanel.appendChild(txt('div', 'lc-panel-title', 'SKILL'));
    var grid = el('div', { class: 'lc-skill-grid' });
    character.skills.forEach(function (sk, i) {
      var box = el('div', { class: 'lc-skill' });
      box.appendChild(txt('div', 'lc-skill-name', 'SKILL 0' + (i + 1) + ' · ' + sk.name));
      box.appendChild(txt('div', 'lc-skill-desc', sk.desc));
      grid.appendChild(box);
    });
    skillPanel.appendChild(grid);
    card.appendChild(skillPanel);

    var weakPanel = el('div', { class: 'lc-panel lc-weakness' });
    weakPanel.appendChild(txt('div', 'lc-weakness-label', '⚠️ WEAKNESS'));
    weakPanel.appendChild(txt('div', 'lc-weakness-title', character.weakness.title));
    weakPanel.appendChild(txt('div', 'lc-weakness-desc', character.weakness.desc));
    card.appendChild(weakPanel);

    var footer = el('div', { class: 'lc-footer' });
    footer.appendChild(txt('span', '', '결 · 연애 캐릭터 카드'));
    footer.appendChild(txt('span', '', '※ 재미로 보는 콘텐츠 등급'));
    card.appendChild(footer);

    return card;
  }

  window.YeonbunLoveCharacter = {
    ELEMENT_THEME: ELEMENT_THEME,
    LOVE_CHARACTERS: LOVE_CHARACTERS,
    getTheme: getTheme,
    getCharacter: getCharacter,
    buildCard: buildCard
  };
})();
