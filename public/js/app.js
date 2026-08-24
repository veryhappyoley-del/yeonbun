(function () {
  'use strict';

  /* ============================================================
   * 1. 사주 계산 엔진 (오프라인 · 외부 API 불필요)
   *    - 일주 기준일: 1900-01-01(KST 정오) = 갑술일
   *    - 월간/시간: 오호둔월 · 오서둔시 조견표
   *    - 절기: 태양 겉보기 황경 저정밀 공식(Meeus, 오차 <=0.01˚)
   * ============================================================ */

  var STEMS = [
    { k: '갑', h: '甲', el: '목', yy: '양' },
    { k: '을', h: '乙', el: '목', yy: '음' },
    { k: '병', h: '丙', el: '화', yy: '양' },
    { k: '정', h: '丁', el: '화', yy: '음' },
    { k: '무', h: '戊', el: '토', yy: '양' },
    { k: '기', h: '己', el: '토', yy: '음' },
    { k: '경', h: '庚', el: '금', yy: '양' },
    { k: '신', h: '辛', el: '금', yy: '음' },
    { k: '임', h: '壬', el: '수', yy: '양' },
    { k: '계', h: '癸', el: '수', yy: '음' }
  ];

  var BRANCHES = [
    { k: '자', h: '子', el: '수', yy: '양', animal: '쥐' },
    { k: '축', h: '丑', el: '토', yy: '음', animal: '소' },
    { k: '인', h: '寅', el: '목', yy: '양', animal: '호랑이' },
    { k: '묘', h: '卯', el: '목', yy: '음', animal: '토끼' },
    { k: '진', h: '辰', el: '토', yy: '양', animal: '용' },
    { k: '사', h: '巳', el: '화', yy: '음', animal: '뱀' },
    { k: '오', h: '午', el: '화', yy: '양', animal: '말' },
    { k: '미', h: '未', el: '토', yy: '음', animal: '양' },
    { k: '신', h: '申', el: '금', yy: '양', animal: '원숭이' },
    { k: '유', h: '酉', el: '금', yy: '음', animal: '닭' },
    { k: '술', h: '戌', el: '토', yy: '양', animal: '개' },
    { k: '해', h: '亥', el: '수', yy: '음', animal: '돼지' }
  ];

  // 시/도 -> 시/군/구 목록 + 경도(경도 보정용). 시/군/구청 소재지 기준 근사값이라
  // 정밀 천문 계산용은 아니고(오차 있어도 수 분 이내), 사주 절입시 보정 목적으로는 충분함.
  var REGIONS = [
    { sido: '서울', sigungu: [
      { name: '강남구', lon: 127.063 }, { name: '강동구', lon: 127.145 },
      { name: '강북구', lon: 127.020 }, { name: '강서구', lon: 126.816 },
      { name: '관악구', lon: 126.952 }, { name: '광진구', lon: 127.084 },
      { name: '구로구', lon: 126.850 }, { name: '금천구', lon: 126.891 },
      { name: '노원구', lon: 127.067 }, { name: '도봉구', lon: 127.030 },
      { name: '동대문구', lon: 127.054 }, { name: '동작구', lon: 126.951 },
      { name: '마포구', lon: 126.909 }, { name: '서대문구', lon: 126.935 },
      { name: '서초구', lon: 127.011 }, { name: '성동구', lon: 127.025 },
      { name: '성북구', lon: 127.014 }, { name: '송파구', lon: 127.111 },
      { name: '양천구', lon: 126.875 }, { name: '영등포구', lon: 126.903 },
      { name: '용산구', lon: 126.978 }, { name: '은평구', lon: 126.928 },
      { name: '종로구', lon: 126.979 }, { name: '중구', lon: 126.994 },
      { name: '중랑구', lon: 127.105 }
    ]},
    { sido: '부산', sigungu: [
      { name: '강서구', lon: 128.933 }, { name: '금정구', lon: 129.090 },
      { name: '기장군', lon: 129.201 }, { name: '남구', lon: 129.083 },
      { name: '동구', lon: 129.034 }, { name: '동래구', lon: 129.078 },
      { name: '부산진구', lon: 129.051 }, { name: '북구', lon: 128.991 },
      { name: '사상구', lon: 128.980 }, { name: '사하구', lon: 128.987 },
      { name: '서구', lon: 129.019 }, { name: '수영구', lon: 129.113 },
      { name: '연제구', lon: 129.083 }, { name: '영도구', lon: 129.065 },
      { name: '중구', lon: 129.033 }, { name: '해운대구', lon: 129.168 }
    ]},
    { sido: '대구', sigungu: [
      { name: '중구', lon: 128.595 }, { name: '동구', lon: 128.633 },
      { name: '서구', lon: 128.551 }, { name: '남구', lon: 128.588 },
      { name: '북구', lon: 128.592 }, { name: '수성구', lon: 128.626 },
      { name: '달서구', lon: 128.524 }, { name: '달성군', lon: 128.430 },
      { name: '군위군', lon: 128.647 }
    ]},
    { sido: '인천', sigungu: [
      { name: '강화군', lon: 126.486 }, { name: '계양구', lon: 126.745 },
      { name: '미추홀구', lon: 126.650 }, { name: '남동구', lon: 126.718 },
      { name: '동구', lon: 126.637 }, { name: '부평구', lon: 126.711 },
      { name: '서구', lon: 126.655 }, { name: '연수구', lon: 126.665 },
      { name: '옹진군', lon: 126.123 }, { name: '중구', lon: 126.622 }
    ]},
    { sido: '광주', sigungu: [
      { name: '동구', lon: 126.923 }, { name: '서구', lon: 126.891 },
      { name: '남구', lon: 126.909 }, { name: '북구', lon: 126.924 },
      { name: '광산구', lon: 126.808 }
    ]},
    { sido: '대전', sigungu: [
      { name: '동구', lon: 127.443 }, { name: '중구', lon: 127.411 },
      { name: '서구', lon: 127.345 }, { name: '유성구', lon: 127.327 },
      { name: '대덕구', lon: 127.434 }
    ]},
    { sido: '울산', sigungu: [
      { name: '중구', lon: 129.332 }, { name: '남구', lon: 129.329 },
      { name: '동구', lon: 129.419 }, { name: '북구', lon: 129.360 },
      { name: '울주군', lon: 129.127 }
    ]},
    { sido: '세종', sigungu: [
      { name: '세종시', lon: 127.282 }
    ]},
    { sido: '경기', sigungu: [
      { name: '가평군', lon: 127.515 }, { name: '고양시', lon: 126.835 },
      { name: '과천시', lon: 127.000 }, { name: '광명시', lon: 126.865 },
      { name: '광주시', lon: 127.257 }, { name: '구리시', lon: 127.139 },
      { name: '군포시', lon: 126.921 }, { name: '김포시', lon: 126.743 },
      { name: '남양주시', lon: 127.240 }, { name: '동두천시', lon: 127.069 },
      { name: '부천시', lon: 126.783 }, { name: '성남시', lon: 127.129 },
      { name: '수원시', lon: 127.010 }, { name: '시흥시', lon: 126.789 },
      { name: '안산시', lon: 126.830 }, { name: '안성시', lon: 127.301 },
      { name: '안양시', lon: 126.927 }, { name: '양주시', lon: 127.046 },
      { name: '양평군', lon: 127.492 }, { name: '여주시', lon: 127.637 },
      { name: '연천군', lon: 127.076 }, { name: '오산시', lon: 127.071 },
      { name: '용인시', lon: 127.178 }, { name: '의왕시', lon: 126.976 },
      { name: '의정부시', lon: 127.048 }, { name: '이천시', lon: 127.443 },
      { name: '파주시', lon: 126.778 }, { name: '평택시', lon: 127.089 },
      { name: '포천시', lon: 127.200 }, { name: '하남시', lon: 127.213 },
      { name: '화성시', lon: 126.829 }
    ]},
    { sido: '강원', sigungu: [
      { name: '원주시', lon: 127.921 }, { name: '춘천시', lon: 127.728 },
      { name: '강릉시', lon: 128.878 }, { name: '동해시', lon: 129.114 },
      { name: '속초시', lon: 128.593 }, { name: '삼척시', lon: 129.166 },
      { name: '홍천군', lon: 127.886 }, { name: '태백시', lon: 128.986 },
      { name: '철원군', lon: 127.445 }, { name: '횡성군', lon: 127.986 },
      { name: '평창군', lon: 128.393 }, { name: '영월군', lon: 128.462 },
      { name: '정선군', lon: 128.730 }, { name: '인제군', lon: 128.279 },
      { name: '고성군', lon: 128.468 }, { name: '양양군', lon: 128.615 },
      { name: '화천군', lon: 127.676 }, { name: '양구군', lon: 127.989 }
    ]},
    { sido: '충북', sigungu: [
      { name: '청주시', lon: 127.490 }, { name: '충주시', lon: 127.877 },
      { name: '제천시', lon: 128.141 }, { name: '보은군', lon: 127.729 },
      { name: '옥천군', lon: 127.568 }, { name: '영동군', lon: 127.790 },
      { name: '증평군', lon: 127.599 }, { name: '진천군', lon: 127.443 },
      { name: '괴산군', lon: 127.814 }, { name: '음성군', lon: 127.681 },
      { name: '단양군', lon: 128.369 }
    ]},
    { sido: '충남', sigungu: [
      { name: '천안시', lon: 127.194 }, { name: '공주시', lon: 127.125 },
      { name: '보령시', lon: 126.594 }, { name: '아산시', lon: 127.004 },
      { name: '서산시', lon: 126.466 }, { name: '논산시', lon: 127.121 },
      { name: '계룡시', lon: 127.226 }, { name: '당진시', lon: 126.667 },
      { name: '금산군', lon: 127.481 }, { name: '부여군', lon: 126.858 },
      { name: '서천군', lon: 126.697 }, { name: '청양군', lon: 126.843 },
      { name: '홍성군', lon: 126.626 }, { name: '예산군', lon: 126.796 },
      { name: '태안군', lon: 126.284 }
    ]},
    { sido: '전북', sigungu: [
      { name: '전주시', lon: 127.149 }, { name: '익산시', lon: 126.954 },
      { name: '군산시', lon: 126.683 }, { name: '정읍시', lon: 126.917 },
      { name: '완주군', lon: 127.148 }, { name: '김제시', lon: 126.908 },
      { name: '남원시', lon: 127.432 }, { name: '고창군', lon: 126.700 },
      { name: '부안군', lon: 126.667 }, { name: '임실군', lon: 127.230 },
      { name: '순창군', lon: 127.167 }, { name: '진안군', lon: 127.412 },
      { name: '장수군', lon: 127.533 }, { name: '무주군', lon: 127.711 }
    ]},
    { sido: '전남', sigungu: [
      { name: '여수시', lon: 127.643 }, { name: '순천시', lon: 127.396 },
      { name: '목포시', lon: 126.394 }, { name: '광양시', lon: 127.649 },
      { name: '나주시', lon: 126.674 }, { name: '무안군', lon: 126.440 },
      { name: '해남군', lon: 126.519 }, { name: '고흥군', lon: 127.333 },
      { name: '화순군', lon: 127.026 }, { name: '영암군', lon: 126.627 },
      { name: '영광군', lon: 126.436 }, { name: '완도군', lon: 126.738 },
      { name: '담양군', lon: 126.991 }, { name: '장성군', lon: 126.768 },
      { name: '보성군', lon: 127.158 }, { name: '신안군', lon: 126.109 },
      { name: '장흥군', lon: 126.917 }, { name: '강진군', lon: 126.768 },
      { name: '함평군', lon: 126.532 }, { name: '진도군', lon: 126.169 },
      { name: '곡성군', lon: 127.263 }, { name: '구례군', lon: 127.464 }
    ]},
    { sido: '경북', sigungu: [
      { name: '포항시', lon: 129.367 }, { name: '경주시', lon: 129.212 },
      { name: '김천시', lon: 128.112 }, { name: '안동시', lon: 128.723 },
      { name: '구미시', lon: 128.354 }, { name: '영주시', lon: 128.586 },
      { name: '영천시', lon: 129.000 }, { name: '상주시', lon: 128.167 },
      { name: '문경시', lon: 128.199 }, { name: '경산시', lon: 128.800 },
      { name: '의성군', lon: 128.615 }, { name: '청송군', lon: 129.052 },
      { name: '영양군', lon: 129.142 }, { name: '영덕군', lon: 129.311 },
      { name: '청도군', lon: 128.785 }, { name: '고령군', lon: 128.297 },
      { name: '성주군', lon: 128.288 }, { name: '칠곡군', lon: 128.461 },
      { name: '예천군', lon: 128.430 }, { name: '봉화군', lon: 128.736 },
      { name: '울진군', lon: 129.320 }, { name: '울릉군', lon: 130.861 }
    ]},
    { sido: '경남', sigungu: [
      { name: '창원시', lon: 128.652 }, { name: '김해시', lon: 128.867 },
      { name: '진주시', lon: 128.124 }, { name: '양산시', lon: 129.036 },
      { name: '거제시', lon: 128.667 }, { name: '통영시', lon: 128.397 },
      { name: '사천시', lon: 128.069 }, { name: '밀양시', lon: 128.749 },
      { name: '함안군', lon: 128.430 }, { name: '거창군', lon: 127.911 },
      { name: '창녕군', lon: 128.490 }, { name: '고성군', lon: 128.282 },
      { name: '하동군', lon: 127.773 }, { name: '합천군', lon: 128.138 },
      { name: '남해군', lon: 127.927 }, { name: '함양군', lon: 127.712 },
      { name: '산청군', lon: 127.871 }, { name: '의령군', lon: 128.269 }
    ]},
    { sido: '제주', sigungu: [
      { name: '제주시', lon: 126.522 }, { name: '서귀포시', lon: 126.497 }
    ]}
  ];

  function norm(n, m) { return ((n % m) + m) % m; }
  function deg2rad(d) { return (d * Math.PI) / 180; }
  function norm360(x) { return norm(x, 360); }

  function toJD(y, m, d, hUT) {
    if (m <= 2) { y -= 1; m += 12; }
    var A = Math.floor(y / 100);
    var B = 2 - A + Math.floor(A / 4);
    return Math.floor(365.25 * (y + 4716)) + Math.floor(30.6001 * (m + 1)) + d + B - 1524.5 + hUT / 24;
  }

  function sunApparentLongitude(jd) {
    var T = (jd - 2451545.0) / 36525;
    var L0 = norm360(280.46646 + 36000.76983 * T + 0.0003032 * T * T);
    var M = norm360(357.52911 + 35999.05029 * T - 0.0001537 * T * T);
    var Mr = deg2rad(M);
    var C = (1.914602 - 0.004817 * T - 0.000014 * T * T) * Math.sin(Mr) +
            (0.019993 - 0.000101 * T) * Math.sin(2 * Mr) +
            0.000289 * Math.sin(3 * Mr);
    var trueLong = L0 + C;
    var Omega = 125.04 - 1934.136 * T;
    return norm360(trueLong - 0.00569 - 0.00478 * Math.sin(deg2rad(Omega)));
  }

  function birthToUT(year, month, day, hour, minute, longitude) {
    var utHour = hour + minute / 60 - 9;
    var y = year, m = month, d = day;
    var lonCorrectionMin = (longitude - 135) * 4;
    utHour += lonCorrectionMin / 60;
    var guard = 0;
    while (utHour < 0 && guard < 10) { utHour += 24; d -= 1; guard++; }
    while (utHour >= 24 && guard < 10) { utHour -= 24; d += 1; guard++; }
    var jsDate = new Date(Date.UTC(y, m - 1, d));
    y = jsDate.getUTCFullYear(); m = jsDate.getUTCMonth() + 1; d = jsDate.getUTCDate();
    return { y: y, m: m, d: d, utHour: utHour };
  }

  function pillarFrom(stemIdx, branchIdx) {
    if (stemIdx === null || stemIdx === undefined || branchIdx === null || branchIdx === undefined) return null;
    var s = STEMS[stemIdx], b = BRANCHES[branchIdx];
    return {
      stem: s.k, stemHanja: s.h, stemElement: s.el, stemYinYang: s.yy,
      branch: b.k, branchHanja: b.h, branchElement: b.el, branchYinYang: b.yy,
      animal: b.animal, label: s.k + b.k, hanja: s.h + b.h
    };
  }

  function calcSaju(input) {
    var year = input.year, month = input.month, day = input.day;
    var hour = input.unknownTime ? 12 : input.hour;
    var minute = input.unknownTime ? 0 : input.minute;
    var lon = input.longitude || 126.978;

    var ut = birthToUT(year, month, day, hour, minute, lon);
    var jd = toJD(ut.y, ut.m, ut.d, ut.utHour);
    var lambda = sunApparentLongitude(jd);

    var adjustedLambda = norm360(lambda - 315);
    var monthIdx0to11 = Math.floor(adjustedLambda / 30);
    var monthBranchIndex = norm(monthIdx0to11 + 2, 12);

    var sajuYear = year;
    if (month < 2) sajuYear = year - 1;
    else if (month === 2 && lambda < 315) sajuYear = year - 1;

    var yearStemIndex = norm(sajuYear - 4, 10);
    var yearBranchIndex = norm(sajuYear - 4, 12);

    var monthStemStart = norm(2 * (yearStemIndex % 5) + 2, 10);
    var monthStemIndex = norm(monthStemStart + monthIdx0to11, 10);

    var baseJD = toJD(1900, 1, 1, 3);
    var birthJDNoon = toJD(ut.y, ut.m, ut.d, 3);
    var diffDays = Math.round(birthJDNoon - baseJD);
    var dayStemIndex = norm(diffDays, 10);
    var dayBranchIndex = norm(diffDays + 10, 12);

    var hourBranchIndex = null, hourStemIndex = null;
    if (!input.unknownTime) {
      if (input.hour >= 23 || input.hour < 1) hourBranchIndex = 0;
      else hourBranchIndex = Math.floor((input.hour + 1) / 2);
      hourStemIndex = norm(dayStemIndex * 2 + hourBranchIndex, 10);
    }

    var yearPillar = pillarFrom(yearStemIndex, yearBranchIndex);
    var monthPillar = pillarFrom(monthStemIndex, monthBranchIndex);
    var dayPillar = pillarFrom(dayStemIndex, dayBranchIndex);
    var hourPillar = pillarFrom(hourStemIndex, hourBranchIndex);

    var wu = { 목: 0, 화: 0, 토: 0, 금: 0, 수: 0 };
    [yearPillar, monthPillar, dayPillar, hourPillar].forEach(function (p) {
      if (!p) return;
      wu[p.stemElement]++; wu[p.branchElement]++;
    });

    var branchesPresent = [yearPillar, monthPillar, dayPillar, hourPillar]
      .filter(Boolean).map(function (p) { return p.branch; });

    return {
      input: input, solarLongitude: lambda, sajuYear: sajuYear,
      year: yearPillar, month: monthPillar, day: dayPillar, hour: hourPillar,
      wuxingCount: wu, branchesPresent: branchesPresent,
      deep: analyzeDeepSaju(
        { year: yearPillar, month: monthPillar, day: dayPillar, hour: hourPillar },
        wu
      )
    };
  }

  /* ============================================================
   * 1-b. 심층 사주 엔진 확장 — 십신 / 지장간 / 합충형파해 / 신강신약 / 용신(간이)
   *      "심층 연애 리포트"(유료)의 AI 분석 근거로 쓰기 위해 calcSaju()의 기존
   *      반환값에 saju.deep 으로만 새로 더한다. 기존 필드(연/월/일/시주, 오행분포,
   *      branchesPresent 등)는 하나도 건드리지 않아서 이 값을 몰라도 되는 다른
   *      기능(오행 분포 카드, 신살, 궁합, 연애 캐릭터 카드 등)은 전혀 영향받지 않는다.
   *      ※ 격국/조후 등은 유파마다 해석이 갈리는 영역이라, 아래는 여러 기초 명리학
   *        자료에서 공통적으로 쓰이는 간이(簡易) 조견표 기반의 콘텐츠용 근사 계산이다
   *        (전문 명리 상담을 대체하지 않음 — 자세한 배경은 saju.deep.note 참고).
   * ============================================================ */

  // 지장간(地藏干) 조견표 — 여기→중기→정기 순으로 배열, 마지막 값이 정기(그 지지의 오행과 일치).
  var HIDDEN_STEMS = {
    자: ['계'], 축: ['계', '신', '기'], 인: ['무', '병', '갑'], 묘: ['을'],
    진: ['을', '계', '무'], 사: ['무', '경', '병'], 오: ['기', '정'], 미: ['정', '을', '기'],
    신: ['무', '임', '경'], 유: ['신'], 술: ['신', '정', '무'], 해: ['갑', '임']
  };

  var EL_GENERATES = { 목: '화', 화: '토', 토: '금', 금: '수', 수: '목' }; // A가 B를 생함
  var EL_CONTROLS = { 목: '토', 화: '금', 토: '수', 금: '목', 수: '화' }; // A가 B를 극함

  // 십신(十神) — 일간(오행+음양) 대비 대상 글자(오행+음양)의 관계.
  function tenGodOf(dayEl, dayYY, el, yy) {
    if (!el) return null;
    if (el === dayEl) return yy === dayYY ? '비견' : '겁재';
    if (EL_GENERATES[el] === dayEl) return yy === dayYY ? '편인' : '정인';
    if (EL_GENERATES[dayEl] === el) return yy === dayYY ? '식신' : '상관';
    if (EL_CONTROLS[el] === dayEl) return yy === dayYY ? '편관' : '정관';
    if (EL_CONTROLS[dayEl] === el) return yy === dayYY ? '편재' : '정재';
    return null;
  }

  // 지지 관계(육합/충/형/파/해) 조견표. 삼형(인사신·축술미)은 두 글자만 있어도 "부분 성립"으로
  // 간이 처리한다(엄밀하게는 세 글자가 모두 있어야 완전히 성립하는 유파도 있음).
  var BRANCH_RELATIONS = {
    육합: [['자', '축'], ['인', '해'], ['묘', '술'], ['진', '유'], ['사', '신'], ['오', '미']],
    충: [['자', '오'], ['축', '미'], ['인', '신'], ['묘', '유'], ['진', '술'], ['사', '해']],
    형: [['인', '사'], ['사', '신'], ['인', '신'], ['축', '술'], ['술', '미'], ['축', '미'], ['자', '묘']],
    파: [['자', '유'], ['오', '묘'], ['사', '신'], ['인', '해'], ['진', '축'], ['술', '미']],
    해: [['자', '미'], ['축', '오'], ['인', '사'], ['묘', '진'], ['신', '해'], ['유', '술']]
  };
  var SELF_PUNISH = ['진', '오', '유', '해']; // 같은 글자가 2개 이상이면 자형(自刑)

  function findBranchRelations(branches) {
    var found = { 육합: [], 충: [], 형: [], 파: [], 해: [], 자형: [] };
    for (var i = 0; i < branches.length; i++) {
      for (var j = i + 1; j < branches.length; j++) {
        var a = branches[i], b = branches[j];
        if (a === b) {
          if (SELF_PUNISH.indexOf(a) !== -1 && found.자형.indexOf(a + a) === -1) found.자형.push(a + a);
          continue;
        }
        Object.keys(BRANCH_RELATIONS).forEach(function (key) {
          BRANCH_RELATIONS[key].forEach(function (pair) {
            var matches = (pair[0] === a && pair[1] === b) || (pair[0] === b && pair[1] === a);
            if (!matches) return;
            var label = a + b, labelRev = b + a;
            if (found[key].indexOf(label) === -1 && found[key].indexOf(labelRev) === -1) {
              found[key].push(label);
            }
          });
        });
      }
    }
    return found;
  }

  // 신강신약(간이): 일간과 같은 오행(비겁) + 일간을 생하는 오행(인성)을 "힘을 보태는 쪽",
  // 나머지(식상·재성·관성)를 "힘을 빼는 쪽"으로 보고, 월지(득령)에 가중치를 살짝 더한 근사치.
  function dayMasterStrength(dayEl, wuxingCount, monthBranchElement) {
    var supportSet = [dayEl];
    Object.keys(EL_GENERATES).forEach(function (el) { if (EL_GENERATES[el] === dayEl) supportSet.push(el); });

    var support = 0, drain = 0;
    Object.keys(wuxingCount).forEach(function (el) {
      var count = wuxingCount[el];
      if (supportSet.indexOf(el) !== -1) support += count; else drain += count;
    });

    var score = support - drain;
    if (monthBranchElement) {
      score += supportSet.indexOf(monthBranchElement) !== -1 ? 1 : -1; // 득령 가중치
    }

    var label = '중화';
    if (score >= 2) label = '신강';
    else if (score <= -2) label = '신약';

    return { label: label, score: score, supportCount: support, drainCount: drain };
  }

  // 용희신(간이): 신약이면 일간을 돕는 오행(비겁·인성 계열), 신강이면 일간의 힘을 덜어내는
  // 오행(식상 계열 우선, 관성 보조), 중화면 오행 분포상 가장 부족한 오행을 보완 후보로 제시.
  function simpleUsefulGod(dayEl, strengthLabel, wuxingCount) {
    if (strengthLabel === '신약') {
      var supportEls = [dayEl];
      Object.keys(EL_GENERATES).forEach(function (el) { if (EL_GENERATES[el] === dayEl) supportEls.push(el); });
      var secondary = supportEls.filter(function (e) { return e !== dayEl; })[0] || dayEl;
      return { primary: dayEl, secondary: secondary, reason: '일간의 힘이 상대적으로 약해서, 같은 오행이나 일간을 생조하는 오행이 도움이 되는 방향이에요.' };
    }
    if (strengthLabel === '신강') {
      return { primary: EL_GENERATES[dayEl], secondary: EL_CONTROLS[dayEl], reason: '일간의 힘이 상대적으로 강해서, 힘을 밖으로 풀어내거나 적절히 눌러주는 오행이 도움이 되는 방향이에요.' };
    }
    var minEl = null, minCount = Infinity;
    Object.keys(wuxingCount).forEach(function (el) {
      if (wuxingCount[el] < minCount) { minCount = wuxingCount[el]; minEl = el; }
    });
    return { primary: minEl, secondary: dayEl, reason: '오행 균형이 비교적 고른 편이라, 사주 원국에서 가장 적은 오행을 보완하는 방향을 참고용으로 제시해요.' };
  }

  function analyzeDeepSaju(pillars, wuxingCount) {
    var year = pillars.year, month = pillars.month, day = pillars.day, hour = pillars.hour;
    if (!day) return null;

    var dayEl = day.stemElement, dayYY = day.stemYinYang;

    function pillarDeep(p, isDayPillar) {
      if (!p) return null;
      return {
        stemTenGod: isDayPillar ? null : tenGodOf(dayEl, dayYY, p.stemElement, p.stemYinYang),
        branchTenGod: tenGodOf(dayEl, dayYY, p.branchElement, p.branchYinYang),
        hiddenStems: HIDDEN_STEMS[p.branch] || []
      };
    }

    var branchesPresent = [year, month, day, hour].filter(Boolean).map(function (p) { return p.branch; });
    var relations = findBranchRelations(branchesPresent);
    var strength = dayMasterStrength(dayEl, wuxingCount, month ? month.branchElement : null);
    var usefulGod = simpleUsefulGod(dayEl, strength.label, wuxingCount);

    return {
      tenGods: {
        year: pillarDeep(year, false), month: pillarDeep(month, false),
        day: pillarDeep(day, true), hour: pillarDeep(hour, false)
      },
      relations: relations,
      dayMasterStrength: strength,
      usefulGod: usefulGod,
      note: '간이 조견표 기반의 콘텐츠용 근사 계산이며, 전문 명리 상담을 대체하지 않습니다.'
    };
  }

  /* ============================================================
   * 2. 신살(연애 관련) 판단
   * ============================================================ */

  var CHEON_EUL_TABLE = {
    갑: ['축', '미'], 무: ['축', '미'], 경: ['축', '미'],
    을: ['자', '신'], 기: ['자', '신'],
    병: ['해', '유'], 정: ['해', '유'],
    신: ['인', '오'],
    임: ['사', '묘'], 계: ['사', '묘']
  };

  function hasAny(set, arr) { return arr.some(function (b) { return set.has(b); }); }

  function findSinSals(saju) {
    var branches = saju.branchesPresent;
    var set = {};
    branches.forEach(function (b) { set[b] = true; });
    var s = { has: function (b) { return !!set[b]; } };
    var found = [];

    if (hasAny(s, ['인', '오', '술']) && s.has('묘')) found.push('도화살');
    if (hasAny(s, ['사', '유', '축']) && s.has('오')) found.push('도화살');
    if (hasAny(s, ['신', '자', '진']) && s.has('유')) found.push('도화살');
    if (hasAny(s, ['해', '묘', '미']) && s.has('자')) found.push('도화살');

    if (hasAny(s, ['인', '오', '술']) && s.has('신')) found.push('역마살');
    if (hasAny(s, ['사', '유', '축']) && s.has('해')) found.push('역마살');
    if (hasAny(s, ['신', '자', '진']) && s.has('인')) found.push('역마살');
    if (hasAny(s, ['해', '묘', '미']) && s.has('사')) found.push('역마살');

    if (hasAny(s, ['인', '오', '술']) && s.has('술')) found.push('화개살');
    if (hasAny(s, ['사', '유', '축']) && s.has('축')) found.push('화개살');
    if (hasAny(s, ['신', '자', '진']) && s.has('진')) found.push('화개살');
    if (hasAny(s, ['해', '묘', '미']) && s.has('미')) found.push('화개살');

    if (saju.day) {
      var gwiList = CHEON_EUL_TABLE[saju.day.stem] || [];
      if (hasAny(s, gwiList)) found.push('천을귀인');
    }

    // 중복 제거
    var uniq = [];
    found.forEach(function (f) { if (uniq.indexOf(f) === -1) uniq.push(f); });
    return uniq;
  }

  var SINSAL_TEXT = {
    도화살: { hanja: '桃花殺', text: '이성에게 자연스럽게 호감을 사는 매력이 있어요. 소개팅이나 새로운 자리에서 인기가 많은 편이라, 마음만 먹으면 인연이 잘 닿아요. 다만 관계가 여러 갈래로 번지지 않도록 한 사람에게 집중하는 선택이 필요한 시기가 올 수 있어요.' },
    역마살: { hanja: '驛馬殺', text: '가만히 있기보다 움직이는 연애를 해요. 여행지·출장지에서의 만남, 장거리 연애, 새로운 모임이나 앱을 통한 인연운이 강한 편이에요. 다만 한 사람과 오래 정착하는 데는 의식적인 노력이 필요할 수 있어요.' },
    화개살: { hanja: '華蓋殺', text: '깊고 예술적인 감성으로 사랑을 해요. 가벼운 만남보다는 정신적으로 통하는 사람을 원하고, 혼자만의 시간도 소중히 여겨요. 그만큼 눈이 높아지기 쉬우니 완벽한 사람을 기다리기보다 조금씩 곁을 내주는 연습이 도움이 돼요.' },
    천을귀인: { hanja: '天乙貴人', text: '연애에서도 귀인의 도움을 받는 운이에요. 지인의 소개나 주변의 응원으로 좋은 인연이 이어지는 경우가 많아요. 사람들과의 관계를 소중히 유지하면 자연스럽게 다음 인연으로 연결돼요.' }
  };

  /* ============================================================
   * 3. 연애 특화 해석 콘텐츠
   * ============================================================ */

  var ELEMENT_LOVE = {
    목: {
      style: '적극적으로 다가가고 관계를 스스로 이끌어가려는 성장형 연애',
      charm: '함께 있으면 자꾸 새로운 걸 시도하게 만드는 에너지, 다정하지만 확실한 리드',
      caution: '내 방식이 맞다는 확신이 강해 상대의 속도를 기다려주지 못할 때가 있어요',
      color: '연둣빛·초록 계열', season: '봄'
    },
    화: {
      style: '감정 표현이 풍부하고 빠르게 몰입하는 열정형 연애',
      charm: '함께 있으면 즐겁고 텐션이 오르는 사교적 매력, 리액션이 좋아 상대를 편하게 함',
      caution: '초반에 확 뜨거워졌다가 상대적으로 빨리 식어 보일 수 있어요, 꾸준함이 관건',
      color: '레드·오렌지 계열', season: '여름'
    },
    토: {
      style: '충분히 관찰하고 신뢰가 쌓인 뒤에야 마음을 여는 안정형 연애',
      charm: '한번 마음을 주면 흔들리지 않는 우직함, 함께 있으면 편안하고 든든한 느낌',
      caution: '변화를 부담스러워해서 관계에 확신을 갖기까지 시간이 오래 걸릴 수 있어요',
      color: '황토·베이지 계열', season: '환절기'
    },
    금: {
      style: '명확한 기준과 태도로 관계를 정리하려는 원칙형 연애',
      charm: '의리 있고 한번 정한 사람에게는 흔들림 없는 신의, 애매한 밀당보다 확실한 태도',
      caution: '자존심이 강해 서운함이나 애정 표현이 서툴러 오해를 살 수 있어요',
      color: '화이트·그레이 계열', season: '가을'
    },
    수: {
      style: '상대의 감정을 예민하게 살피면서도 속내는 잘 드러내지 않는 감성형 연애',
      charm: '눈치가 빠르고 상대 컨디션을 잘 챙기는 섬세함, 은은하게 다가가는 여운 있는 매력',
      caution: '속마음을 숨기는 편이라 상대가 내 마음을 확신하지 못해 답답해할 수 있어요',
      color: '네이비·블랙 계열', season: '겨울'
    }
  };

  var YINYANG_LOVE = {
    양: '마음이 가면 먼저 다가가고 감정을 직접적으로 표현하는 능동형이에요.',
    음: '마음이 가도 먼저 티내기보다 관찰하며 신중하게 다가가는 수용형이에요.'
  };

  var BALANCE_LOVE = {
    목: { many: '호기심이 많아 연애를 자기계발처럼 여기고, 새로운 사람을 만나는 데 거리낌이 없어요.', few: '관계를 시작할 때 유독 소극적일 수 있어요. 작은 용기 하나가 인연을 바꿔요.' },
    화: { many: '감정 기복이 있는 편이라 연애 초반 몰입도가 특히 강해요.', few: '애정 표현이 서툴러서 상대가 마음을 오해할 수 있어요. 말로 표현하는 연습이 필요해요.' },
    토: { many: '안정적인 관계를 최우선으로 여기고 변화를 별로 좋아하지 않아요.', few: '관계에 확신을 갖기까지 유독 시간이 오래 걸릴 수 있어요.' },
    금: { many: '기준이 뚜렷해서 애매한 밀당보다 확실한 관계를 선호해요.', few: '거절이나 갈등 상황에서 우유부단해질 수 있어요.' },
    수: { many: '상대 감정을 예민하게 캐치하지만 정작 자기 감정은 잘 숨기는 편이에요.', few: '상대 마음을 읽는 데 서툴러서 눈치가 없다는 말을 들을 수 있어요.' }
  };

  function analyzeLove(saju) {
    if (!saju.day) return null;
    var dayEl = saju.day.stemElement;
    var dayYY = saju.day.stemYinYang;
    var base = ELEMENT_LOVE[dayEl];
    var avg = 8 / 5;
    var strong = [], weak = [];
    Object.keys(saju.wuxingCount).forEach(function (el) {
      var c = saju.wuxingCount[el];
      if (c >= avg * 1.8) strong.push(el);
      else if (c === 0 || c < avg * 0.5) weak.push(el);
    });
    var sinsals = findSinSals(saju);
    return { dayEl: dayEl, dayYY: dayYY, base: base, strong: strong, weak: weak, sinsals: sinsals };
  }

  /* ============================================================
   * 4. 궁합 로직
   * ============================================================ */

  var GENERATES = { 목: '화', 화: '토', 토: '금', 금: '수', 수: '목' };
  var CONTROLS = { 목: '토', 토: '수', 수: '화', 화: '금', 금: '목' };

  var YUKHAP = { 자: '축', 축: '자', 인: '해', 해: '인', 묘: '술', 술: '묘', 진: '유', 유: '진', 사: '신', 신: '사', 오: '미', 미: '오' };
  var CHUNG = { 자: '오', 오: '자', 축: '미', 미: '축', 인: '신', 신: '인', 묘: '유', 유: '묘', 진: '술', 술: '진', 사: '해', 해: '사' };
  var SAMHAP_GROUP = {
    인: 'A', 오: 'A', 술: 'A',
    사: 'B', 유: 'B', 축: 'B',
    신: 'C', 자: 'C', 진: 'C',
    해: 'D', 묘: 'D', 미: 'D'
  };

  function elementRelation(elA, elB) {
    if (elA === elB) return 'same';
    if (GENERATES[elA] === elB || GENERATES[elB] === elA) return 'generate';
    if (CONTROLS[elA] === elB || CONTROLS[elB] === elA) return 'control';
    return 'neutral';
  }

  function calcCompat(sajuA, sajuB) {
    var elA = sajuA.day.stemElement, elB = sajuB.day.stemElement;
    var rel = elementRelation(elA, elB);
    var score = 65;
    var notes = [];

    if (rel === 'generate') { score += 22; notes.push('두 사람의 일간(日干) 오행이 서로를 낳아주는 상생 관계예요. 한쪽이 자연스럽게 다른 한쪽에게 힘이 되어 주는 흐름이라 편안하게 오래 가는 궁합이에요.'); }
    else if (rel === 'same') { score += 8; notes.push('두 사람의 일간 오행이 같아요(비화). 취향과 리듬이 비슷해서 잘 통하지만, 닮은 만큼 같은 지점에서 부딪힐 수 있어요. 다름을 인정하는 대화가 도움이 돼요.'); }
    else if (rel === 'control') { score -= 14; notes.push('두 사람의 일간 오행이 서로를 극하는 관계예요. 초반엔 강하게 끌리지만 자기주장이 부딪히기 쉬운 궁합이라, 의식적으로 배려하는 노력이 관계를 더 단단하게 만들어요.'); }
    else { notes.push('두 사람의 일간 오행은 직접적인 생·극 관계는 아니에요. 무난하게 시작해서 서로 맞춰가는 유형의 궁합이에요.'); }

    var branchA = sajuA.day.branch, branchB = sajuB.day.branch;
    if (YUKHAP[branchA] === branchB) { score += 12; notes.push('태어난 날의 지지(일지)가 육합(六合) 관계예요. 서로의 부족한 부분을 자연스럽게 채워주는, 궁합에서 특히 좋게 보는 조합이에요.'); }
    if (CHUNG[branchA] === branchB) { score -= 10; notes.push('일지가 충(沖) 관계라 서로 자극을 주고받는 사이예요. 갈등이 생기면 크게 느껴질 수 있지만, 그만큼 서로를 성장시키는 자극제가 되기도 해요.'); }
    if (SAMHAP_GROUP[branchA] && SAMHAP_GROUP[branchA] === SAMHAP_GROUP[branchB] && branchA !== branchB) {
      score += 6; notes.push('일지가 같은 삼합(三合) 그룹이라 지향하는 삶의 방향이 비슷해요.');
    }

    score = Math.max(15, Math.min(97, Math.round(score)));

    var levelLabel;
    if (score >= 85) levelLabel = '천생연분에 가까운 궁합';
    else if (score >= 70) levelLabel = '무난하고 편안한 궁합';
    else if (score >= 50) levelLabel = '노력이 필요한 역동적인 궁합';
    else levelLabel = '서로 많이 다른, 배움이 큰 궁합';

    return { score: score, levelLabel: levelLabel, notes: notes, elA: elA, elB: elB, rel: rel };
  }

  /* ============================================================
   * 5. 고민 상담 가이드 (룰 기반)
   * ============================================================ */

  var CONCERNS = [
    { key: 'crush', label: '짝사랑 중', icon: '🌱' },
    { key: 'seom', label: '썸 타는 중', icon: '🌤️' },
    { key: 'bored', label: '연인과 권태기', icon: '🌫️' },
    { key: 'breakup', label: '이별 후유증', icon: '🍂' },
    { key: 'reunion', label: '재회 고민', icon: '🔄' },
    { key: 'confidence', label: '연애 자신감 부족', icon: '🕯️' }
  ];

  var CONCERN_PATTERN = {
    crush: { 목: '마음이 앞서서 티가 먼저 나버렸을 가능성이 커요.', 화: '이미 혼자 상상 속에서 관계를 여러 번 진전시켰을 수 있어요.', 토: '고백보다 먼저 상대를 충분히 파악하려다 타이밍을 놓치기 쉬워요.', 금: '마음을 들키는 게 자존심 상해서 오히려 더 무심한 척했을 수 있어요.', 수: '티 내지 않고 혼자 감정을 키우다 지쳐가고 있을 가능성이 커요.' },
    seom: { 목: '관계를 빨리 정의하고 싶어서 상대를 재촉하게 될 수 있어요.', 화: '연락이 뜸해지면 감정 기복이 크게 흔들릴 수 있어요.', 토: '확신이 서기 전까지는 곁을 잘 안 주려 해서 상대가 답답해할 수 있어요.', 금: '애매한 상태를 오래 못 견뎌서 빨리 결론 내리고 싶어할 수 있어요.', 수: '상대의 온도를 촘촘히 분석하느라 정작 내 마음 표현은 뒷전일 수 있어요.' },
    bored: { 목: '새로운 자극이 없으면 관계에 흥미를 잃은 듯 느껴질 수 있어요.', 화: '초반의 뜨거움이 식으면서 권태가 유독 크게 느껴질 수 있어요.', 토: '편안함에 익숙해져서 노력을 소홀히 하고 있었을 수 있어요.', 금: '반복되는 패턴에 지쳐 마음의 문을 조용히 닫아가고 있을 수 있어요.', 수: '겉으론 무덤덤해 보여도 속으로는 서운함이 꽤 쌓였을 수 있어요.' },
    breakup: { 목: '빨리 다음 단계로 나아가고 싶어서 감정을 충분히 못 추스를 수 있어요.', 화: '감정이 크게 요동치다가도 의외로 빠르게 다시 일어설 힘이 있어요.', 토: '이별을 받아들이는 데 시간이 오래 걸리는 편이에요.', 금: '괜찮은 척하지만 자존심 때문에 속으로 더 오래 앓을 수 있어요.', 수: '겉으로 티 내지 않고 혼자 조용히 감정을 정리하는 편이에요.' },
    reunion: { 목: '다시 관계를 이끌어보고 싶은 마음에 성급하게 연락할 수 있어요.', 화: '그리움이 크게 밀려올 때 감정적으로 연락하기 쉬워요.', 토: '돌아가고 싶은 마음이 있어도 신중하게 재고 또 재는 편이에요.', 금: '자존심 때문에 먼저 다가가기가 유독 어려울 수 있어요.', 수: '재회를 원하면서도 속마음을 잘 안 드러내 상대가 눈치채기 어려울 수 있어요.' },
    confidence: { 목: '자신감이 없을 땐 오히려 과하게 밀어붙이는 방식으로 감출 수 있어요.', 화: '겉으론 밝아 보여도 속으론 거절에 대한 두려움이 클 수 있어요.', 토: '안전한 관계 밖으로 나가는 걸 유독 두려워하는 편이에요.', 금: '높은 기준 때문에 스스로를 자꾸 검열하게 될 수 있어요.', 수: '자기 확신이 부족할 때 더 깊이 생각에 잠기며 위축될 수 있어요.' }
  };

  var CONCERN_ADVICE = {
    crush: '고백이냐 포기냐의 이분법보다, 상대가 나를 자연스럽게 알아갈 접점을 하나씩 늘려가는 게 먼저예요. 공통 관심사로 대화를 시작하거나, 가벼운 일상 연락부터 리듬을 만들어보세요. 관계는 한 번의 큰 사건보다 여러 번의 작은 접촉으로 시작되는 경우가 많아요.',
    seom: '지금은 관계를 정의하려 애쓰기보다 서로의 리듬을 관찰할 때예요. 답장 속도나 연락 빈도 하나하나에 일희일비하기보다, 실제로 만났을 때의 온도에 더 집중해보세요. 다만 애매한 상태가 한 달 이상 이어진다면, 한 번은 직접 마음을 확인하는 대화가 필요해요.',
    bored: '권태기는 관계가 끝났다는 신호가 아니라 다음 단계로 넘어가는 길목인 경우가 많아요. 새로운 자극(작은 여행, 새로운 취미)을 함께 시도하거나, 최근 서운했던 점을 감정적이지 않은 타이밍에 담백하게 이야기해보세요. 무엇을 참고 있었는지 서로 확인하는 것만으로도 숨통이 트여요.',
    breakup: '지금은 억지로 괜찮아지려 하기보다 감정을 있는 그대로 흘려보내는 시간이 필요해요. SNS로 상대의 근황을 확인하는 습관은 회복을 늦추니 잠시 거리를 두는 게 좋아요. 일상의 작은 루틴(운동, 만남, 취미)을 다시 채워가다 보면 감정도 자연스럽게 자리를 잡아요.',
    reunion: '재회를 고민할 땐 "그때가 그리운 것"과 "이 사람과 다시 맞을 수 있는 것"을 구분하는 게 중요해요. 헤어진 원인이 실제로 달라졌는지 점검하지 않고 감정만으로 다시 시작하면 같은 지점에서 반복될 수 있어요. 연락하기 전에 무엇이 달라져야 하는지 먼저 스스로 정리해보세요.',
    confidence: '연애 자신감은 상대에게 잘 보이는 기술이 아니라, 거절받아도 내가 무너지지 않는다는 감각에서 나와요. 작은 관계(친구, 동료)에서부터 솔직하게 표현하는 연습을 쌓아가면 자연스럽게 단단해져요. 완벽한 상태에서 연애를 시작하는 사람은 없다는 것도 기억해 두세요.'
  };

  var currentSajuA = null; // 나의 연애 사주 결과 (탭1) — 상담 가이드에서 재사용
  var currentCompat = null; // 궁합 결과 (탭2) — 프리미엄 궁합 리포트/공유 카드에서 재사용

  /* ============================================================
   * 6. DOM 렌더링 유틸
   * ============================================================ */

  var elBar = { 목: 'var(--wood)', 화: 'var(--fire)', 토: 'var(--earth)', 금: 'var(--metal)', 수: 'var(--water)' };

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else node.setAttribute(k, attrs[k]);
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(c); });
    return node;
  }
  function txt(tag, cls, text) { return el(tag, { class: cls }, [document.createTextNode(text)]); }

  // (2026-08-24 추가) 궁합 폼의 "현재 관계"/"지금 가장 궁금한 것" 칩·카드는 컨테이너 안에서
  // 최대 1개만 선택되는 단일 선택 토글이다(라디오 버튼과 같은 동작이지만 버튼 UI로).
  // 같은 걸 다시 누르면 선택 해제(선택 사항이라 "고르지 않음"도 유효한 상태).
  function wireSingleSelect(containerId, itemClass) {
    var container = document.getElementById(containerId);
    if (!container) return;
    container.querySelectorAll('.' + itemClass).forEach(function (item) {
      item.addEventListener('click', function () {
        var wasActive = item.classList.contains('active');
        container.querySelectorAll('.' + itemClass).forEach(function (el2) { el2.classList.remove('active'); });
        if (!wasActive) item.classList.add('active');
      });
    });
  }

  function getSingleSelectValue(containerId, dataAttr) {
    var container = document.getElementById(containerId);
    if (!container) return null;
    var activeEl = container.querySelector('.active');
    return activeEl ? activeEl.getAttribute('data-' + dataAttr) : null;
  }

  function renderMyeongsik(saju) {
    var grid = el('div', { class: 'myeongsik' });
    var roles = [
      ['시주', saju.hour], ['일주', saju.day], ['월주', saju.month], ['년주', saju.year]
    ];
    roles.forEach(function (pair) {
      var role = pair[0], p = pair[1];
      if (!p) {
        grid.appendChild(el('div', { class: 'pillar' }, [
          txt('div', 'role', role),
          txt('div', 'hangul', '시간 미상')
        ]));
        return;
      }
      grid.appendChild(el('div', { class: 'pillar' }, [
        txt('div', 'role', role),
        el('div', { class: 'hanja' }, [
          el('span', { class: 'stem-el el-' + p.stemElement }, [document.createTextNode(p.stemHanja)]),
          el('span', { class: 'branch-el el-' + p.branchElement }, [document.createTextNode(p.branchHanja)])
        ]),
        txt('div', 'hangul', p.stem + p.branch + ' · ' + p.animal + '(' + p.branchElement + ')')
      ]));
    });
    return grid;
  }

  function renderOheang(wu) {
    var wrap = el('div', { class: 'oheang-list' });
    var max = Math.max(1, Math.max.apply(null, Object.values(wu)));
    ['목', '화', '토', '금', '수'].forEach(function (k) {
      var c = wu[k];
      var row = el('div', { class: 'oheang-row' });
      row.appendChild(txt('div', 'lab', k));
      var track = el('div', { class: 'oheang-track' });
      var fill = el('div', { class: 'oheang-fill' });
      fill.style.width = (max ? (c / Math.max(max, 5)) * 100 : 0) + '%';
      fill.style.background = elBar[k];
      track.appendChild(fill);
      row.appendChild(track);
      row.appendChild(txt('div', 'cnt', String(c)));
      wrap.appendChild(row);
    });
    return wrap;
  }

  function block(title, text) {
    var wrap = el('div', {});
    wrap.appendChild(txt('h3', '', title));
    wrap.appendChild(txt('p', '', text));
    return wrap;
  }

  function renderSingleResult(saju, name) {
    var out = document.getElementById('s-result');
    out.innerHTML = '';
    var love = analyzeLove(saju);

    currentSajuA = { saju: saju, love: love, name: name };

    // 1. 사주 명식 — 언제나 바로 보이는 첫 화면(예전엔 접이식 안에 있던 걸 맨 위로 뺌).
    var myeongsikCard = el('div', { class: 'card' });
    var heading = (name ? name + '님의 ' : '') + '사주 명식';
    myeongsikCard.appendChild(txt('h2', '', heading));
    myeongsikCard.appendChild(renderMyeongsik(saju));
    myeongsikCard.appendChild(txt('div', 'day-note', '일간(나를 상징하는 글자) · ' + saju.day.stem + saju.day.stemElement + ' — 아래 연애 해석의 중심이 되는 글자예요'));
    out.appendChild(myeongsikCard);

    // 2. 나머지 상세 사주 풀이(오행분포/신살/연애 스타일 텍스트)는 접이식으로 원하는 사람만 보게 함.
    //    (연애 캐릭터 카드보다 위에 둬서, 사주 명식 → 자세히 보기 → 캐릭터 카드 순서가 되게 함.)
    // 예전엔 기본적으로 접혀 있었는데, 사용자 피드백으로 처음부터 펼쳐서 보여주도록 변경(2026-08-24).
    var detailToggle = el('details', { class: 'saju-detail-toggle', open: 'open' });
    detailToggle.appendChild(txt('summary', '', '자세한 사주 풀이 보기'));

    var card = el('div', { class: 'card' });
    card.appendChild(txt('h3', '', '오행 분포'));
    card.appendChild(renderOheang(saju.wuxingCount));

    var result = el('div', { class: 'result-block' });
    result.appendChild(block('연애 스타일', love.base.style + '. ' + YINYANG_LOVE[love.dayYY]));
    result.appendChild(block('매력 포인트', love.base.charm + '.'));
    result.appendChild(block('이럴 땐 조심', love.base.caution + '.'));

    if (love.strong.length || love.weak.length) {
      var extra = [];
      love.strong.forEach(function (elk) { extra.push(BALANCE_LOVE[elk].many); });
      love.weak.forEach(function (elk) { extra.push(BALANCE_LOVE[elk].few); });
      if (extra.length) result.appendChild(block('사주 전체에서 더 보이는 특징', extra.join(' ')));
    }

    if (love.sinsals.length) {
      var sinWrap = el('div', {});
      sinWrap.appendChild(txt('h3', '', '눈에 띄는 신살(神殺)'));
      var badgeRow = el('div', { class: 'badge-row' });
      love.sinsals.forEach(function (s) {
        badgeRow.appendChild(el('span', { class: 'badge seal' }, [document.createTextNode(s + ' · ' + SINSAL_TEXT[s].hanja)]));
      });
      sinWrap.appendChild(badgeRow);
      love.sinsals.forEach(function (s) {
        sinWrap.appendChild(txt('p', '', SINSAL_TEXT[s].text));
      });
      result.appendChild(sinWrap);
    }

    card.appendChild(result);
    detailToggle.appendChild(card);
    out.appendChild(detailToggle);

    // 3. "연애 캐릭터 카드"(love type card) — 게임 캐릭터 카드처럼 유형명/스탯/스킬을 보여주는 카드.
    //    카드 바로 아래에 "이 카드 공유하기" 버튼을 붙여서(reports.js) 카드 자체를 공유할 수 있게 함.
    var characterCard = null;
    if (window.YeonbunLoveCharacter) {
      characterCard = window.YeonbunLoveCharacter.buildCard(currentSajuA);
      out.appendChild(characterCard);
    }
    if (characterCard && window.YeonbunReports) {
      window.YeonbunReports.attachCardShare(characterCard, out);
    }

    // 4. "심층 연애 리포트 보기" 구매 CTA(공유 카드 버튼은 위 캐릭터 카드 공유로 대체돼서 여기선 뺌).
    var ctaHost = el('div', { class: 'card' });
    out.appendChild(ctaHost);
    if (window.YeonbunReports) window.YeonbunReports.attachSingleCTA(ctaHost, currentSajuA);

    renderGuideEmptyOrKeep();
  }

  // (2026-08-24 추가) relationshipStage/primaryConcern/concernDetail — 궁합 폼에서 선택한
  // "현재 관계 단계"/"지금 가장 궁금한 것"/자유 입력 궁금증. 둘 다 선택 사항이라 null일 수
  // 있음. reports.js buildTwoPersonInput()이 이 값을 그대로 프리미엄 리포트 input에 담아
  // CompatibilityReportType의 프롬프트가 톤/강조 챕터를 조정하는 데 쓴다.
  //
  // (2026-08-24 추가) 화면 구성을 결제 유도가 자연스럽게 이어지도록 재배치:
  //   점수 → 궁합 유형 카드(무료, 공유 가능) → 풀이 텍스트(기존 무료 콘텐츠) →
  //   무료 티저(compat_overview 챕터를 실제로 미리 생성 — 일부는 완전 공개, 일부는
  //   진짜 콘텐츠를 그대로 둔 채 시각적으로만 블러 처리) → 12개 챕터 목차 미리보기
  //   (이탈 지점에 가깝게 위로 당김) → 구매 버튼+신뢰 배지(attachCompatCTA).
  function renderCompatResult(sajuA, sajuB, nameA, nameB, relationshipStage, primaryConcern, concernDetail) {
    var out = document.getElementById('c-result');
    out.innerHTML = '';
    var compat = calcCompat(sajuA, sajuB);
    var card = el('div', { class: 'card' });
    card.appendChild(txt('h2', '', (nameA || 'A') + ' × ' + (nameB || 'B') + ' 궁합'));

    var scoreWrap = el('div', { class: 'compat-score-wrap' });
    scoreWrap.appendChild(el('div', { class: 'compat-score' }, [
      document.createTextNode(String(compat.score)), el('span', {}, [document.createTextNode('/100')])
    ]));
    scoreWrap.appendChild(txt('div', 'compat-score-label', compat.levelLabel));
    card.appendChild(scoreWrap);

    var badgeRow = el('div', { class: 'badge-row' }, [
      el('span', { class: 'badge indigo' }, [document.createTextNode((nameA || 'A') + ' 일간: ' + sajuA.day.stem + '(' + sajuA.day.stemElement + ')')]),
      el('span', { class: 'badge indigo' }, [document.createTextNode((nameB || 'B') + ' 일간: ' + sajuB.day.stem + '(' + sajuB.day.stemElement + ')')])
    ]);
    card.appendChild(badgeRow);

    // "궁합 유형 카드" — 룰 기반(AI 호출 없음, 비용 0원)이라 계산 즉시 바로 보여줄 수 있고,
    // 연애 캐릭터 카드와 같은 공유 캡처 방식(attachCardShare)을 그대로 재사용한다.
    if (window.YeonbunCompatType && window.YeonbunReports) {
      var typeState = { sajuA: sajuA, sajuB: sajuB, nameA: nameA, nameB: nameB, compat: compat };
      var typeCard = window.YeonbunCompatType.buildCard(typeState);
      card.appendChild(typeCard);
      window.YeonbunReports.attachCardShare(typeCard, card, {
        footerCta: '나도 우리 궁합 유형 확인하기 👉',
        filename: 'gyeol-compat-type-card.png',
        title: '결 — 궁합 유형 카드',
        text: '우리 궁합 유형 나왔어! 너도 확인해볼래?'
      });
    }

    var result = el('div', { class: 'result-block' });
    compat.notes.forEach(function (n, i) {
      result.appendChild(block('풀이 ' + (i + 1), n));
    });
    card.appendChild(result);

    // 무료 티저(compat_overview 실제 생성) — 결제 안내를 클릭하기 전, 가장 궁금할 시점에
    // "점수가 왜 이렇게 나왔는지"에 대한 진짜 답 일부를 미리 보여준다.
    var teaserHost = el('div', { class: 'compat-teaser' });
    card.appendChild(teaserHost);
    startCompatPreview(teaserHost, compat, relationshipStage, primaryConcern, concernDetail);

    // 12개 챕터 목차 미리보기를 CTA 버튼보다 위(무료 티저 바로 아래)로 당겨서, 이탈하기
    // 전에 "이런 챕터가 더 있다"는 걸 보여준다(정적 데이터라 비용 0원).
    if (window.YeonbunReports && window.YeonbunReports.buildTocPreview) {
      var toc = window.YeonbunReports.buildTocPreview('compatibility');
      if (toc) card.appendChild(toc);
    }

    out.appendChild(card);

    currentCompat = {
      sajuA: sajuA, sajuB: sajuB, nameA: nameA, nameB: nameB, compat: compat,
      relationshipStage: relationshipStage || null,
      primaryConcern: primaryConcern || null,
      concernDetail: concernDetail || null
    };

    if (window.YeonbunReports) window.YeonbunReports.attachCompatCTA(card, currentCompat);
  }

  // "지금 가장 궁금한 것" 카드(4종)의 한글 라벨 — resources/views/reports/partials/blocks/
  // concern_answer.blade.php의 $concernLabels와 반드시 같은 문구를 유지해야, 결제 전
  // 무료 티저와 결제 후 리포트에서 같은 질문 문구가 보인다.
  var CONCERN_LABELS = {
    continuity: '지속 가능성 — 잘 맞는지, 이대로 이어질 수 있는지',
    growth: '관계 발전 — 연애·결혼 등 다음 단계로 갈 수 있을지',
    flow: '앞으로의 흐름 — 가까워질 시기·멀어질 시기가 궁금할 때',
    friction: '충돌 완화 — 싸움·오해·마찰이 반복되는 이유'
  };

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  // 로딩 상태를 스피너+스켈레톤으로 눈에 띄게 보여준다 — 아무 표시 없이 텍스트만 있으면
  // "멈춘 건가?" 싶다가 콘텐츠가 갑자기 튀어나와 놀라게 되므로, 지금 뭔가 만들어지고
  // 있다는 걸 계속 티내고 스켈레톤 높이로 레이아웃 점프도 줄인다.
  function renderTeaserLoading(host) {
    host.innerHTML = '';
    host.appendChild(txt('div', 'compat-teaser-label', '🔍 이 궁합, 더 자세히 보면'));

    var loading = el('div', { class: 'compat-teaser-loading' });

    var row = el('div', { class: 'compat-teaser-loading-row' });
    row.appendChild(el('span', { class: 'compat-teaser-spinner' }));
    row.appendChild(el('span', null, [document.createTextNode('실제 리포트 내용 일부를 미리 만들고 있어요…')]));
    loading.appendChild(row);

    var skeleton = el('div', { class: 'compat-teaser-skeleton' });
    skeleton.appendChild(el('div', { class: 'compat-teaser-skel-block' }));
    skeleton.appendChild(el('div', { class: 'compat-teaser-skel-line', style: 'width:100%' }));
    skeleton.appendChild(el('div', { class: 'compat-teaser-skel-line', style: 'width:92%' }));
    skeleton.appendChild(el('div', { class: 'compat-teaser-skel-line', style: 'width:68%' }));
    loading.appendChild(skeleton);

    host.appendChild(loading);
  }

  // 결제 전에도 실제로 생성된 콘텐츠를 그대로 보여준다(더미 텍스트 아님) — 1문단은 완전
  // 공개, 나머지는 진짜 텍스트를 그대로 둔 채 CSS로만 블러 처리(.compat-teaser-fade)해서
  // "읽다가 흐려지는" 느낌을 준다. 결제 후에도 같은 내용이 그대로 이어지므로(GenerateReportJob
  // 이 같은 입력 해시로 이 캐시를 재사용) 미리 본 것과 다른 내용이 나올 걱정이 없다.
  function renderTeaserContent(host, content, primaryConcern, concernDetail) {
    host.innerHTML = '';
    host.appendChild(txt('div', 'compat-teaser-label', '🔍 이 궁합, 더 자세히 보면'));

    var trimmedDetail = concernDetail ? String(concernDetail).trim() : '';
    var question = trimmedDetail !== '' ? trimmedDetail : (CONCERN_LABELS[primaryConcern] || null);
    var answer = content && typeof content.concern_answer === 'string' ? content.concern_answer.trim() : '';

    if (question && answer !== '') {
      var answerCard = el('div', { class: 'rpt-concern-answer' });
      answerCard.appendChild(txt('div', 'rpt-concern-answer-label', '🔍 회원님이 가장 궁금해하신 것'));
      answerCard.appendChild(txt('div', 'rpt-concern-answer-question', question));
      answerCard.appendChild(txt('div', 'rpt-concern-answer-body', answer));
      host.appendChild(answerCard);
    }

    var paragraphs = (content && Array.isArray(content.paragraphs))
      ? content.paragraphs.filter(function (p) { return typeof p === 'string' && p !== ''; })
      : [];

    if (paragraphs.length) {
      host.appendChild(txt('p', 'rpt-p', paragraphs[0]));

      if (paragraphs.length > 1) {
        var fadeWrap = el('div', { class: 'compat-teaser-fade' });
        paragraphs.slice(1).forEach(function (p) {
          fadeWrap.appendChild(txt('p', 'rpt-p', p));
        });
        host.appendChild(fadeWrap);
      }
    }

    if (question && answer !== '' || paragraphs.length) {
      host.appendChild(txt('div', 'compat-teaser-cta', '전체 내용은 궁합분석 리포트에서 이어져요 — 아래 12개 챕터도 함께 준비돼 있어요.'));
    } else {
      // 콘텐츠가 비정상적으로 비어있으면(스키마 검증 실패 등) 조용히 숨김 — 무료 화면 흐름을 방해하지 않음.
      host.innerHTML = '';
    }
  }

  function renderTeaserHidden(host) {
    // 생성 실패/타임아웃이면 조용히 숨김 — 에러 문구로 무료 화면 흐름을 방해하지 않는다.
    host.innerHTML = '';
  }

  // 결제 전 compat_overview 미리보기를 요청/폴링한다. 서버(ChapterPreviewController)가
  // 같은 입력 해시로 이미 만든 게 있으면 API를 다시 안 부르고 바로 돌려주므로, 같은
  // 조합을 다시 계산해도(예: 뒤로 갔다 다시 계산) 비용이 중복으로 들지 않는다.
  function startCompatPreview(host, compat, relationshipStage, primaryConcern, concernDetail) {
    if (!window.YeonbunBilling || !window.YeonbunBilling.chapterPreviewsUrl) return;

    renderTeaserLoading(host);

    var input = {
      score: compat.score,
      levelLabel: compat.levelLabel,
      notes: compat.notes,
      relation: compat.rel,
      relationshipStage: relationshipStage || null,
      primaryConcern: primaryConcern || null,
      concernDetail: concernDetail || null
    };

    var attempts = 0;
    var maxAttempts = 20; // 20 * 1.5초 ≈ 30초

    function poll() {
      attempts += 1;

      fetch(window.YeonbunBilling.chapterPreviewsUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken()
        },
        body: JSON.stringify({ type: 'compatibility', chapter: 'compat_overview', input: input })
      })
        .then(function (res) { return res.ok ? res.json() : Promise.reject(new Error('preview request failed')); })
        .then(function (data) {
          if (data.status === 'ready') {
            renderTeaserContent(host, data.content, primaryConcern, concernDetail);
            return;
          }
          if (data.status === 'failed' || attempts >= maxAttempts) {
            renderTeaserHidden(host);
            return;
          }
          setTimeout(poll, 1500);
        })
        .catch(function () {
          if (attempts < maxAttempts) {
            setTimeout(poll, 1500);
          } else {
            renderTeaserHidden(host);
          }
        });
    }

    poll();
  }

  function renderGuideResult(concernKey) {
    var out = document.getElementById('guide-result');
    out.innerHTML = '';
    var meta = CONCERNS.filter(function (c) { return c.key === concernKey; })[0];
    if (!meta) return;

    var card = el('div', { class: 'card guide-card' });
    var header = el('div', { style: 'display:flex;align-items:center;gap:10px;margin-bottom:8px;' });
    header.appendChild(el('div', { class: 'stamp' }, [document.createTextNode('緣')]));
    header.appendChild(txt('h2', '', meta.icon + ' ' + meta.label));
    card.appendChild(header);

    var elKey = currentSajuA ? currentSajuA.love.dayEl : null;
    var patternText = elKey ? CONCERN_PATTERN[concernKey][elKey] : '아직 사주 정보가 없어서 일반적인 경향으로 안내할게요. ‘나의 연애 사주’ 탭에서 먼저 풀이를 보면 이 부분이 더 맞춤화돼요.';
    var nameLabel = currentSajuA && currentSajuA.name ? currentSajuA.name + '님은' : '지금';

    card.appendChild(txt('div', 'field-label', '상황 요약'));
    card.appendChild(txt('p', '', nameLabel + ' "' + meta.label + '" 단계에 있어요.'));

    card.appendChild(txt('div', 'field-label', '사주로 보는 나의 패턴'));
    card.appendChild(txt('p', '', patternText));

    card.appendChild(txt('div', 'field-label', '조언'));
    card.appendChild(txt('p', '', CONCERN_ADVICE[concernKey]));

    if (elKey) {
      card.appendChild(txt('div', 'field-label', '이것만은 주의'));
      card.appendChild(txt('p', '', ELEMENT_LOVE[elKey].caution + '.'));
    }

    out.appendChild(card);
  }

  function renderGuideEmptyOrKeep() {
    var active = document.querySelector('.concern-chip.active');
    if (active) renderGuideResult(active.getAttribute('data-key'));
  }

  /* ============================================================
   * 7. 이벤트 와이어링
   * ============================================================ */

  var REGION_NONE = '__NONE__';

  function fillSigunguSelect(sigunguSel, sido) {
    sigunguSel.innerHTML = '';
    if (sido === REGION_NONE) {
      sigunguSel.disabled = true;
      var opt = document.createElement('option');
      opt.value = '135'; opt.textContent = '-';
      sigunguSel.appendChild(opt);
      return;
    }
    sigunguSel.disabled = false;
    var region = null;
    for (var i = 0; i < REGIONS.length; i++) {
      if (REGIONS[i].sido === sido) { region = REGIONS[i]; break; }
    }
    if (!region) return;
    region.sigungu.forEach(function (c) {
      var o = document.createElement('option');
      o.value = c.lon; o.textContent = c.name;
      sigunguSel.appendChild(o);
    });
  }

  function wireSidoSigungu(sidoId, sigunguId) {
    var sidoSel = document.getElementById(sidoId);
    var sigunguSel = document.getElementById(sigunguId);

    REGIONS.forEach(function (r) {
      var opt = document.createElement('option');
      opt.value = r.sido; opt.textContent = r.sido;
      sidoSel.appendChild(opt);
    });
    var direct = document.createElement('option');
    direct.value = REGION_NONE; direct.textContent = '보정 없음(표준시 그대로)';
    sidoSel.appendChild(direct);

    sidoSel.addEventListener('change', function () {
      fillSigunguSelect(sigunguSel, sidoSel.value);
    });
    fillSigunguSelect(sigunguSel, sidoSel.value);
  }

  function fillCitySelects() {
    wireSidoSigungu('s-sido', 's-sigungu');
    wireSidoSigungu('c-sido-a', 'c-sigungu-a');
    wireSidoSigungu('c-sido-b', 'c-sigungu-b');
  }

  function readSingleForm() {
    var year = parseInt(document.getElementById('s-year').value, 10);
    var month = parseInt(document.getElementById('s-month').value, 10);
    var day = parseInt(document.getElementById('s-day').value, 10);
    var hour = parseInt(document.getElementById('s-hour').value, 10);
    var minute = parseInt(document.getElementById('s-minute').value, 10);
    var unknown = document.getElementById('s-unknown').checked;
    var lon = parseFloat(document.getElementById('s-sigungu').value);
    var name = document.getElementById('s-name').value.trim();

    if (!year || !month || !day || (!unknown && (isNaN(hour) || isNaN(minute)))) {
      alert('생년월일을 정확히 입력해 주세요. 시간을 모르면 "시간을 몰라요"에 체크해 주세요.');
      return null;
    }
    return {
      year: year, month: month, day: day,
      hour: unknown ? null : hour, minute: unknown ? null : minute,
      unknownTime: unknown, longitude: isNaN(lon) ? 126.978 : lon, name: name
    };
  }

  function bindEvents() {
    wireSingleSelect('c-stage-row', 'compat-stage-chip');
    wireSingleSelect('c-concern-grid', 'compat-concern-card');

    document.querySelectorAll('.tab-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.panel').forEach(function (p) { p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('panel-' + btn.getAttribute('data-tab')).classList.add('active');
      });
    });

    document.getElementById('s-submit').addEventListener('click', function () {
      var f = readSingleForm();
      if (!f) return;
      var saju = calcSaju(f);
      renderSingleResult(saju, f.name);
    });

    document.getElementById('c-submit').addEventListener('click', function () {
      var ya = parseInt(document.getElementById('c-year-a').value, 10);
      var ma = parseInt(document.getElementById('c-month-a').value, 10);
      var da = parseInt(document.getElementById('c-day-a').value, 10);
      var ha = parseInt(document.getElementById('c-hour-a').value, 10);
      var na = parseInt(document.getElementById('c-minute-a').value, 10);
      var unknownA = document.getElementById('c-unknown-a').checked || isNaN(ha) || isNaN(na);
      var lonA = parseFloat(document.getElementById('c-sigungu-a').value);

      var yb = parseInt(document.getElementById('c-year-b').value, 10);
      var mb = parseInt(document.getElementById('c-month-b').value, 10);
      var db = parseInt(document.getElementById('c-day-b').value, 10);
      var hb = parseInt(document.getElementById('c-hour-b').value, 10);
      var nb = parseInt(document.getElementById('c-minute-b').value, 10);
      var unknownB = document.getElementById('c-unknown-b').checked || isNaN(hb) || isNaN(nb);
      var lonB = parseFloat(document.getElementById('c-sigungu-b').value);

      var nameA = document.getElementById('c-name-a').value.trim();
      var nameB = document.getElementById('c-name-b').value.trim();

      if (!ya || !ma || !da || !yb || !mb || !db) {
        alert('두 사람의 생년월일을 모두 입력해 주세요.');
        return;
      }
      var sajuA = calcSaju({
        year: ya, month: ma, day: da,
        hour: unknownA ? null : ha, minute: unknownA ? null : na,
        unknownTime: unknownA, longitude: isNaN(lonA) ? 126.978 : lonA
      });
      var sajuB = calcSaju({
        year: yb, month: mb, day: db,
        hour: unknownB ? null : hb, minute: unknownB ? null : nb,
        unknownTime: unknownB, longitude: isNaN(lonB) ? 126.978 : lonB
      });

      var stage = getSingleSelectValue('c-stage-row', 'stage');
      var concern = getSingleSelectValue('c-concern-grid', 'concern');
      var concernDetail = document.getElementById('c-concern-detail').value.trim().slice(0, 40);

      renderCompatResult(sajuA, sajuB, nameA, nameB, stage, concern, concernDetail);
    });

    var grid = document.getElementById('concern-grid');
    CONCERNS.forEach(function (c) {
      var chip = el('button', { class: 'concern-chip', 'data-key': c.key }, [document.createTextNode(c.icon + ' ' + c.label)]);
      chip.addEventListener('click', function () {
        document.querySelectorAll('.concern-chip').forEach(function (x) { x.classList.remove('active'); });
        chip.classList.add('active');
        renderGuideResult(c.key);
      });
      grid.appendChild(chip);
    });
  }

  fillCitySelects();
  bindEvents();

  // ---- AI 상담 탭(public/js/chat.js)에서 방금 계산한 사주를 읽어갈 수 있도록 노출 ----
  window.YeonbunApp = {
    getSajuContext: function () {
      if (!currentSajuA) return null;
      var saju = currentSajuA.saju, love = currentSajuA.love;
      return {
        name: currentSajuA.name || null,
        pillars: {
          year: saju.year.label,
          month: saju.month.label,
          day: saju.day.label,
          hour: saju.hour ? saju.hour.label : null
        },
        dayElement: love.dayEl,
        dayYinYang: love.dayYY,
        wuxingCount: saju.wuxingCount,
        sinsals: love.sinsals
      };
    }
  };
})();
