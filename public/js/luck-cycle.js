/**
 * 대운(大運)/세운(歲運)/월운(月運) 계산 엔진 — "짝사랑 탈출" 리포트의 "📅 언제 움직여야
 * 하는가" 챕터를 위해 2026-08-31에 신설.
 *
 * 배경: 이 챕터는 대운/세운을 연도→월 단위로 계산해서 "우선순위 시기"를 뽑아야 하는데,
 * 이 코드베이스엔 원래 대운/세운 계산이 전혀 없었다(claude/로드맵.md 4단계 메모 참고 —
 * "대운/세운 계산이 코드 어디에도 없음"). AI에게 "정확한 연도/월을 계산해서 알려달라"고
 * 시키면 모델이 그럴듯하지만 근거 없는 날짜를 지어낼 위험이 커서(사주 계산은 결정론적
 * 산수라 AI가 즉석에서 정확히 해낼 수 있는 영역이 아님), 실제 계산은 여기서 결정론적으로
 * 수행하고 AI에게는 "이미 계산된 후보 중에서" 우선순위를 골라 자연스러운 문장으로 풀어
 * 쓰는 역할만 맡긴다(App\ReportTypes\Definitions\UnrequitedLoveReportType의
 * moving_timing 챕터 참고) — AI가 존재하지 않는 시기를 지어낼 수 있는 여지 자체를 없앤다.
 *
 * 이 파일은 public/js/app.js가 이미 만들어서 검증해 둔 천문 계산(Meeus 태양 겉보기 황경
 * 저정밀 공식, 율리우스일 변환)과 60갑자 조견표를 window.YeonbunSajuEngine으로 넘겨받아
 * 재사용한다(app.js를 이 스크립트보다 먼저 로드해야 함). 새로 계산하는 것은 딱 두 가지뿐:
 *   1. 임의의 미래 날짜(생일이 아닌 날짜)의 연주/월주 — calcSaju()의 연주/월주 산출 공식을
 *      "생일"이 아니라 "지금부터 3년 안의 아무 날짜"에 그대로 적용한 것뿐, 새 천문 공식은
 *      없다.
 *   2. 대운수(大運數) — 생일에서 가장 가까운 절기 경계(24절기 중 "절")까지의 날짜 수를
 *      3일=1년으로 환산. 절기 경계의 정확한 날짜는 태양 겉앙 황경이 30°의 배수(315°
 *      기준)를 지나는 순간을 뉴턴법으로 몇 차례 반복해서 찾는다(태양의 겉보기 황경은
 *      연중 단조증가라 수렴이 빠르고 안정적이다).
 *
 * 순행/역행, 대운 60갑자 전개, 십신 기반 우선순위 스코어링은 모두 명리학의 표준적인
 * 방법을 그대로 코드로 옮긴 것이며, 실제 결과는 이 사이트의 다른 콘텐츠와 마찬가지로
 * "통계적·문화적 참고용"이다.
 */
(function () {
  'use strict';

  function ready() {
    return !!(window.YeonbunSajuEngine && window.YeonbunSajuEngine.STEMS);
  }

  function stemIndexOf(letter) {
    var stems = window.YeonbunSajuEngine.STEMS;
    for (var i = 0; i < stems.length; i++) if (stems[i].k === letter) return i;
    return null;
  }

  function branchIndexOf(letter) {
    var branches = window.YeonbunSajuEngine.BRANCHES;
    for (var i = 0; i < branches.length; i++) if (branches[i].k === letter) return i;
    return null;
  }

  function norm360(x) { return window.YeonbunSajuEngine.norm(x, 360); }

  var DAY_PER_DEGREE = 365.2422 / 360; // 태양이 1도 이동하는 데 걸리는 평균 일수(≈0.9856의 역수)

  // 태양 겉보기 황경이 targetLambda(0~360)에 도달하는 율리우스일을 뉴턴법으로 근사한다.
  // 연중 겉보기 황경은 단조증가라(작은 이심률 보정항이 있어도 방향이 바뀌지 않음) 5회
  // 반복이면 시(時) 단위 이하로 수렴한다.
  function findBoundaryJD(targetLambda, guessJD) {
    var jd = guessJD;
    for (var i = 0; i < 6; i++) {
      var lambda = window.YeonbunSajuEngine.sunApparentLongitude(jd);
      var diff = ((targetLambda - lambda + 540) % 360) - 180; // -180~180 범위의 최단 각도차
      jd += diff * DAY_PER_DEGREE;
    }
    return jd;
  }

  // calcSaju()의 연주/월주 산출 공식을 "생일"이 아닌 임의의 날짜에 그대로 적용한다.
  // 정오(KST) 기준으로 근사해서 계산한다 — 몇 시간 오차가 있어도 "이 달의 월주가 무엇인지"
  // 라는 질문엔 영향이 없다(절기 경계 순간에 정확히 걸치는 극히 드문 경우만 하루 정도
  // 어긋날 수 있음).
  function pillarsForDate(year, month, day) {
    var E = window.YeonbunSajuEngine;
    var lon = 126.978; // 서울 기준. 지역별 절입시 차이는 수 분 이내라 이 용도에는 영향 없음.
    var ut = E.birthToUT(year, month, day, 12, 0, lon);
    var jd = E.toJD(ut.y, ut.m, ut.d, ut.utHour);
    var lambda = E.sunApparentLongitude(jd);

    var adjustedLambda = norm360(lambda - 315);
    var monthIdx0to11 = Math.floor(adjustedLambda / 30);
    var monthBranchIndex = E.norm(monthIdx0to11 + 2, 12);

    var sajuYear = year;
    if (month < 2) sajuYear = year - 1;
    else if (month === 2 && lambda < 315) sajuYear = year - 1;

    var yearStemIndex = E.norm(sajuYear - 4, 10);
    var yearBranchIndex = E.norm(sajuYear - 4, 12);
    var monthStemStart = E.norm(2 * (yearStemIndex % 5) + 2, 10);
    var monthStemIndex = E.norm(monthStemStart + monthIdx0to11, 10);

    return {
      jd: jd, solarLongitude: lambda, sajuYear: sajuYear,
      year: E.pillarFrom(yearStemIndex, yearBranchIndex),
      month: E.pillarFrom(monthStemIndex, monthBranchIndex)
    };
  }

  // 세운(그 해의 연주) — 연중 아무 날짜(절입 경계에서 먼 6월 1일)로 계산하면 사주년도
  // 보정과 무관하게 항상 calcSaju()와 같은 "연도-4" 공식과 정확히 일치한다.
  function yearPillar(year) {
    return pillarsForDate(year, 6, 1).year;
  }

  // 월운(그 달의 월주) — 절기 경계는 달력의 1일과 정확히 안 맞기 때문에, 그 달의 절기
  // 경계에서 비교적 먼 15일을 기준일로 계산한다.
  function monthPillar(year, month) {
    return pillarsForDate(year, month, 15).month;
  }

  /**
   * 대운(大運) — 태어난 달의 월주(月柱)에서 순행(다음 간지) 또는 역행(이전 간지)으로
   * 10년 단위로 전개한다. 방향은 "년간의 음양 + 성별"로 정해지는 명리학의 표준 규칙:
   * 양년생 남자 · 음년생 여자 = 순행, 음년생 남자 · 양년생 여자 = 역행.
   *
   * 대운수(첫 대운이 시작하는 나이)는 생일에서 가장 가까운 절기 경계까지의 날짜 수를
   * 3일=1년으로 환산한 값이다(순행이면 다음 절기까지, 역행이면 이전 절기까지).
   *
   * @param {Object} saju calcSaju()의 반환값(A 또는 B 한 사람 분) — solarLongitude/input/
   *                       year/month 필드가 필요하다.
   * @param {'male'|'female'} gender
   * @param {number} [count] 몇 개의 대운을 전개할지(기본 8개 = 80년치).
   */
  function daeunList(saju, gender, count) {
    if (!ready() || !saju || !saju.year || !saju.month || !saju.input) return null;
    if (gender !== 'male' && gender !== 'female') return null;
    count = count || 8;

    var E = window.YeonbunSajuEngine;
    var input = saju.input;
    var lon = input.longitude || 126.978;
    var hour = input.unknownTime ? 12 : input.hour;
    var minute = input.unknownTime ? 0 : input.minute;
    var ut = E.birthToUT(input.year, input.month, input.day, hour, minute, lon);
    var birthJD = E.toJD(ut.y, ut.m, ut.d, ut.utHour);
    var lambda = saju.solarLongitude;

    var adjustedLambda = norm360(lambda - 315);
    var monthIdx0to11 = Math.floor(adjustedLambda / 30);
    var withinDeg = adjustedLambda - monthIdx0to11 * 30;

    var prevTarget = norm360(315 + 30 * monthIdx0to11);
    var nextTarget = norm360(315 + 30 * (monthIdx0to11 + 1));
    var prevJD = findBoundaryJD(prevTarget, birthJD - withinDeg * DAY_PER_DEGREE);
    var nextJD = findBoundaryJD(nextTarget, birthJD + (30 - withinDeg) * DAY_PER_DEGREE);

    var daysToPrev = Math.max(0, birthJD - prevJD);
    var daysToNext = Math.max(0, nextJD - birthJD);

    var yearYinYang = saju.year.stemYinYang; // '양' | '음'
    var forward = (yearYinYang === '양' && gender === 'male') || (yearYinYang === '음' && gender === 'female');
    var daysUsed = forward ? daysToNext : daysToPrev;
    var daeunNumber = Math.max(1, Math.round(daysUsed / 3));

    var monthStemIndex = stemIndexOf(saju.month.stem);
    var monthBranchIndex = branchIndexOf(saju.month.branch);
    var dir = forward ? 1 : -1;

    var list = [];
    for (var i = 1; i <= count; i++) {
      var stemIdx = E.norm(monthStemIndex + i * dir, 10);
      var branchIdx = E.norm(monthBranchIndex + i * dir, 12);
      var startAge = daeunNumber + (i - 1) * 10;
      list.push({ order: i, startAge: startAge, endAge: startAge + 9, pillar: E.pillarFrom(stemIdx, branchIdx) });
    }

    return { direction: forward ? '순행' : '역행', daeunNumber: daeunNumber, list: list };
  }

  // 연애 관점의 십신 가중치 — 전통적으로 여자에게는 관성(정관·편관)이, 남자에게는
  // 재성(정재·편재)이 이성/애인을 상징한다고 본다. 식신은 매력·표현력과 관련된 십신으로
  // 성별과 무관하게 소폭 가점을 준다. 비견/겁재(경쟁·주관 강화)는 "내가 움직이기보다
  // 상대에게 끌려다니기 쉬운 시기"로 보아 소폭 감점한다.
  function romanceWeight(tenGod, gender) {
    if (!tenGod) return 0;
    if (gender === 'female' && (tenGod === '정관' || tenGod === '편관')) return tenGod === '정관' ? 3 : 2;
    if (gender === 'male' && (tenGod === '정재' || tenGod === '편재')) return tenGod === '정재' ? 3 : 2;
    if (tenGod === '식신') return 1;
    if (tenGod === '비견' || tenGod === '겁재') return -1;
    return 0;
  }

  var MONTH_LABELS = ['1월', '2월', '3월', '4월', '5월', '6월', '7월', '8월', '9월', '10월', '11월', '12월'];

  var YUKHAP_PAIRS = [['자', '축'], ['인', '해'], ['묘', '술'], ['진', '유'], ['사', '신'], ['오', '미']];
  function isYukhapWithDay(dayBranch, branch) {
    return YUKHAP_PAIRS.some(function (pair) {
      return (pair[0] === dayBranch && pair[1] === branch) || (pair[1] === dayBranch && pair[0] === branch);
    });
  }

  // (2026-08-31 리팩터) priorityWindows가 원래 자기 안에서만 쓰던 "한 달 채점" 로직을
  // 별도 함수로 뽑아냈다 — "다시, 우리"(재회 전략) 리포트의 monthlyCalendar()도 똑같은
  // 채점 기준(십신 가중치+용신+육합)을 그대로 써야 해서, 로직을 두 곳에 복사해 두면
  // 나중에 한쪽만 고치는 실수가 생기기 쉽기 때문이다. 점수 산식 자체는 예전
  // priorityWindows 안에 있던 것과 완전히 동일하다(순서/가중치 전혀 안 바꿈) — 기존
  // "짝사랑 탈출" 리포트가 이미 이 계산 결과를 쓰고 있어서 값이 달라지면 안 된다.
  function scoreMonth(dayEl, dayYY, dayBranch, usefulGod, gender, y, m) {
    var E = window.YeonbunSajuEngine;
    var mp = monthPillar(y, m);
    var stemTenGod = E.tenGodOf(dayEl, dayYY, mp.stemElement, mp.stemYinYang);
    var branchTenGod = E.tenGodOf(dayEl, dayYY, mp.branchElement, mp.branchYinYang);

    var score = 0;
    var reasons = [];

    var stemW = romanceWeight(stemTenGod, gender);
    var branchW = romanceWeight(branchTenGod, gender);
    score += stemW + branchW;
    if (stemW > 0) reasons.push('이 달의 천간이 ' + stemTenGod + ' 기운이라 이성운이 움직이기 좋은 시기예요.');
    if (branchW > 0 && branchTenGod !== stemTenGod) reasons.push('이 달의 지지도 ' + branchTenGod + ' 기운을 더해줘요.');

    if (usefulGod && (mp.stemElement === usefulGod.primary || mp.branchElement === usefulGod.primary)) {
      score += 2;
      reasons.push('평소 도움이 되는 오행(용신)과 맞아떨어지는 달이라 전체적인 기운의 흐름이 좋아요.');
    } else if (usefulGod && (mp.stemElement === usefulGod.secondary || mp.branchElement === usefulGod.secondary)) {
      score += 1;
    }

    if (isYukhapWithDay(dayBranch, mp.branch)) {
      score += 2;
      reasons.push('일지와 육합(六合)을 이루는 달이라 관계가 자연스럽게 가까워지기 좋은 흐름이에요.');
    }

    return { year: y, month: m, periodLabel: y + '년 ' + MONTH_LABELS[m - 1], score: score, reasons: reasons };
  }

  // 점수 상위 시기가 한 달에 몰리지 않도록, 이미 고른 시기와 minGapMonths개월 이내면
  // 건너뛰고 다음으로 높은 시기를 고른다(연도 안에서 우선순위 1~3위가 고르게 퍼지도록).
  // priorityWindows와 monthlyCalendar의 topWindows가 공유하는 선택 로직.
  function pickTopN(scored, topN, minGapMonths) {
    var sorted = scored.slice().sort(function (a, b) { return b.score - a.score; });
    var picked = [];
    for (var j = 0; j < sorted.length && picked.length < topN; j++) {
      var cand = sorted[j];
      var tooClose = picked.some(function (p) {
        var gap = Math.abs((p.year * 12 + p.month) - (cand.year * 12 + cand.month));
        return gap < minGapMonths;
      });
      if (!tooClose) picked.push(cand);
    }
    return picked;
  }

  function scoreMonthsAhead(saju, gender, monthsAhead) {
    var dayEl = saju.day.stemElement, dayYY = saju.day.stemYinYang;
    var dayBranch = saju.day.branch;
    var usefulGod = saju.deep.usefulGod || null;

    var now = new Date();
    var startYear = now.getFullYear(), startMonth = now.getMonth() + 1;

    var scored = [];
    for (var i = 1; i <= monthsAhead; i++) {
      var totalMonth = (startMonth - 1) + i;
      var y = startYear + Math.floor(totalMonth / 12);
      var m = (totalMonth % 12) + 1;
      scored.push(scoreMonth(dayEl, dayYY, dayBranch, usefulGod, gender, y, m));
    }
    return scored;
  }

  /**
   * 앞으로 monthsAhead개월 동안의 월운을 한 달씩 훑어서, 이 사람의 일간(day master) ·
   * 용신(usefulGod) · 성별을 기준으로 "다가가기 좋은 시기"에 점수를 매긴다. 실제 계산된
   * 월주만 후보로 삼으므로, 뒤에서 AI는 이 목록 중에서 고르기만 하고 존재하지 않는
   * 날짜를 지어낼 수 없다.
   *
   * @param {Object} saju calcSaju() 반환값(A 또는 B 한 사람 분).
   * @param {'male'|'female'} gender
   * @param {Object} [opts] { monthsAhead(기본 36), topN(기본 3), minGapMonths(기본 2) }
   * @return {Array<{year, month, periodLabel, score, reasons}>}
   */
  function priorityWindows(saju, gender, opts) {
    if (!ready() || !saju || !saju.day || !saju.deep) return [];
    opts = opts || {};
    var monthsAhead = opts.monthsAhead || 36;
    var topN = opts.topN || 3;
    var minGapMonths = opts.minGapMonths == null ? 2 : opts.minGapMonths;

    var scored = scoreMonthsAhead(saju, gender, monthsAhead);

    return pickTopN(scored, topN, minGapMonths);
  }

  // (2026-08-31 신설) "다시, 우리"(재회 전략) 리포트의 "재회 타이밍 캘린더" 챕터 전용.
  // priorityWindows는 상위 N개만 골라 반환하지만, 이 캘린더는 화면에 12개월 표 전체를
  // 그대로 보여줘야 해서 전체 달을 다 반환한다. 별점(1~5)은 이 사람의 monthsAhead개월
  // 점수 분포 안에서의 상대적 위치(최소~최대를 1~5로 min-max 정규화)이고, 추천 행동은
  // 별점 구간 + 직전 달 대비 상승/하락 추세로 정한다 — 같은 별 3개라도 막 오르는
  // 중이면 "가벼운 연락", 막 꺾이는 중이면 "감정 점검"으로 다르게 안내해서, 예시로 준
  // "9월⭐⭐/기다리기 → 10월⭐⭐⭐/가벼운연락 → 11월⭐⭐⭐⭐/만남시도 → 12월⭐⭐⭐⭐⭐/관계진전 →
  // 1월⭐⭐⭐/감정점검" 같은 오르내리는 흐름을 재현한다. AI는 이 표의 숫자를 하나도 만들지
  // 않고(리포트 화면이 $report->input에서 직접 읽어 렌더링), 상위 3개 시기(topWindows)에
  // 대한 짧은 코멘트만 덧붙인다(reunion_calendar 챕터, priority_timing 블록 재사용).
  //
  // @param {Object} saju calcSaju() 반환값(personA 기준).
  // @param {'male'|'female'} gender
  // @param {Object} [opts] { monthsAhead(기본 12) }
  // @return {{ months: Array<{year,month,periodLabel,stars,score,action,reasons}>, topWindows: Array }}
  function monthlyCalendar(saju, gender, opts) {
    if (!ready() || !saju || !saju.day || !saju.deep) return { months: [], topWindows: [] };
    opts = opts || {};
    var monthsAhead = opts.monthsAhead || 12;

    var scored = scoreMonthsAhead(saju, gender, monthsAhead);

    var scores = scored.map(function (s) { return s.score; });
    var minScore = Math.min.apply(null, scores);
    var maxScore = Math.max.apply(null, scores);
    var range = maxScore - minScore;

    var ACTION_BY_TIER = { 1: '기다리기', 2: '기다리기', 4: '만남 시도', 5: '관계 진전' };

    var months = scored.map(function (s, idx) {
      var stars = range > 0 ? Math.round(1 + (4 * (s.score - minScore)) / range) : 3;
      stars = Math.max(1, Math.min(5, stars));

      var action;
      if (stars === 3) {
        var prevScore = idx > 0 ? scored[idx - 1].score : s.score;
        action = s.score > prevScore ? '가벼운 연락' : '감정 점검';
      } else {
        action = ACTION_BY_TIER[stars];
      }

      return {
        year: s.year, month: s.month, periodLabel: s.periodLabel,
        stars: stars, score: s.score, action: action, reasons: s.reasons
      };
    });

    return { months: months, topWindows: pickTopN(scored, 3, 2) };
  }

  window.YeonbunLuckCycle = {
    yearPillar: yearPillar,
    monthPillar: monthPillar,
    daeunList: daeunList,
    priorityWindows: priorityWindows,
    monthlyCalendar: monthlyCalendar
  };
})();
