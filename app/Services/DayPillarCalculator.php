<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

/**
 * "오늘의 운세" 구독을 위해 처음 만든 서버 사이드(PHP) 사주 계산기.
 *
 * 지금까지 이 프로젝트의 사주 계산은 전부 public/js/app.js(calcSaju)에만 있었다 —
 * 사용자가 폼을 눌러야 브라우저가 계산해서 서버로 결과만 보내는 구조라, 서버 혼자서는
 * 아무 계산도 못 했다. 구독형 "오늘의 운세"는 사용자 접속 없이 매일 새벽 배치가 혼자
 * 계산해야 하므로, 이 클래스가 그 최소한(일주만)을 PHP로 옮겨 담당한다.
 *
 * **의도적으로 일주(day pillar)까지만 포팅했다.** 월주/시주/대운 방향에 필요한 절기
 * (태양 겉보기 황경) 계산은 훨씬 복잡하고(app.js의 Meeus 저정밀 공식), "오늘의
 * 운세"에는 필요 없다 — 일주 대 오늘의 일진 비교만으로 충분하다.
 *
 * **알고리즘은 전부 public/js/app.js의 toJD()/birthToUT()/일주 계산부(1900-01-01
 * KST 정오 = 갑술일 기준)를 그대로 옮겨적은 것이다.** 새로 발명한 계산이 아니다 —
 * 새로 짠 코드가 기존 JS와 미묘하게 어긋나면 "계산기에서 본 내 일주"와 "오늘의
 * 운세가 말하는 내 일주"가 서로 달라지는 신뢰도 문제가 생기므로, 반드시
 * `php artisan fortune:verify-day-pillar`(검증용, 아래 참고)로 JS와 대조해야 한다.
 */
class DayPillarCalculator
{
    /** @var array<int, array{k: string, h: string, el: string, yy: string}> */
    private const STEMS = [
        ['k' => '갑', 'h' => '甲', 'el' => '목', 'yy' => '양'],
        ['k' => '을', 'h' => '乙', 'el' => '목', 'yy' => '음'],
        ['k' => '병', 'h' => '丙', 'el' => '화', 'yy' => '양'],
        ['k' => '정', 'h' => '丁', 'el' => '화', 'yy' => '음'],
        ['k' => '무', 'h' => '戊', 'el' => '토', 'yy' => '양'],
        ['k' => '기', 'h' => '己', 'el' => '토', 'yy' => '음'],
        ['k' => '경', 'h' => '庚', 'el' => '금', 'yy' => '양'],
        ['k' => '신', 'h' => '辛', 'el' => '금', 'yy' => '음'],
        ['k' => '임', 'h' => '壬', 'el' => '수', 'yy' => '양'],
        ['k' => '계', 'h' => '癸', 'el' => '수', 'yy' => '음'],
    ];

    /** @var array<int, array{k: string, h: string, el: string, yy: string, animal: string}> */
    private const BRANCHES = [
        ['k' => '자', 'h' => '子', 'el' => '수', 'yy' => '양', 'animal' => '쥐'],
        ['k' => '축', 'h' => '丑', 'el' => '토', 'yy' => '음', 'animal' => '소'],
        ['k' => '인', 'h' => '寅', 'el' => '목', 'yy' => '양', 'animal' => '호랑이'],
        ['k' => '묘', 'h' => '卯', 'el' => '목', 'yy' => '음', 'animal' => '토끼'],
        ['k' => '진', 'h' => '辰', 'el' => '토', 'yy' => '양', 'animal' => '용'],
        ['k' => '사', 'h' => '巳', 'el' => '화', 'yy' => '음', 'animal' => '뱀'],
        ['k' => '오', 'h' => '午', 'el' => '화', 'yy' => '양', 'animal' => '말'],
        ['k' => '미', 'h' => '未', 'el' => '토', 'yy' => '음', 'animal' => '양'],
        ['k' => '신', 'h' => '申', 'el' => '금', 'yy' => '양', 'animal' => '원숭이'],
        ['k' => '유', 'h' => '酉', 'el' => '금', 'yy' => '음', 'animal' => '닭'],
        ['k' => '술', 'h' => '戌', 'el' => '토', 'yy' => '양', 'animal' => '개'],
        ['k' => '해', 'h' => '亥', 'el' => '수', 'yy' => '음', 'animal' => '돼지'],
    ];

    // 상생(生): 목생화, 화생토, 토생금, 금생수, 수생목.
    private const GENERATES = ['목' => '화', '화' => '토', '토' => '금', '금' => '수', '수' => '목'];

    // 상극(剋): 목극토, 토극수, 수극화, 화극금, 금극목.
    private const CONTROLS = ['목' => '토', '토' => '수', '수' => '화', '화' => '금', '금' => '목'];

    private const DEFAULT_LONGITUDE = 126.978; // 서울 기본값 — app.js와 동일한 기본 경도.

    /**
     * 생년월일시로 일주를 계산한다. 시간을 모르면(unknownTime) app.js와 똑같이
     * 정오(12:00)로 취급한다 — 일주 계산 자체가 "그 날짜"만 보고 시각은 자정 부근
     * 롤오버 보정에만 쓰이므로 시간 미상이어도 결과가 흔들리지 않는다.
     */
    public function pillarForBirth(
        int $year,
        int $month,
        int $day,
        ?int $hour,
        ?int $minute,
        ?float $longitude = null,
    ): array {
        $hour ??= 12;
        $minute ??= 0;
        $longitude ??= self::DEFAULT_LONGITUDE;

        $ut = $this->birthToUt($year, $month, $day, $hour, $minute, $longitude);

        return $this->dayPillarFromCalendarDate($ut['y'], $ut['m'], $ut['d']);
    }

    /** 임의의 달력 날짜(주로 "오늘")의 일진. 시간/경도 보정이 필요 없다(생일이 아니라 그냥 날짜라서). */
    public function pillarForDate(int $year, int $month, int $day): array
    {
        return $this->dayPillarFromCalendarDate($year, $month, $day);
    }

    /** 오늘(Asia/Seoul, config('app.timezone'))의 일진. */
    public function todayPillar(): array
    {
        $today = now();

        return $this->pillarForDate((int) $today->format('Y'), (int) $today->format('n'), (int) $today->format('j'));
    }

    /**
     * 오행 $from → $to 관계. 오늘의 운세 문구는 "일간(나) 기준으로 오늘의 기운이
     * 나를 돕는지/소모시키는지/부딪히는지"를 말해야 하므로, relationOf(내 일간의 오행,
     * 오늘 일간의 오행) 순서로 호출한다.
     *
     * @return 'same'|'generates'|'generated_by'|'controls'|'controlled_by'|'unknown'
     */
    public function relationOf(string $from, string $to): string
    {
        if ($from === $to) {
            return 'same';
        }

        if ((self::GENERATES[$from] ?? null) === $to) {
            return 'generates'; // 내가 오늘을 생함(내 에너지를 씀)
        }

        if ((self::GENERATES[$to] ?? null) === $from) {
            return 'generated_by'; // 오늘이 나를 생함(도움을 받음)
        }

        if ((self::CONTROLS[$from] ?? null) === $to) {
            return 'controls'; // 내가 오늘을 극함
        }

        if ((self::CONTROLS[$to] ?? null) === $from) {
            return 'controlled_by'; // 오늘이 나를 극함(부딪힘)
        }

        return 'unknown';
    }

    private function dayPillarFromCalendarDate(int $y, int $m, int $d): array
    {
        $baseJd = $this->toJd(1900, 1, 1, 3.0);
        $jdNoon = $this->toJd($y, $m, $d, 3.0);
        $diffDays = (int) round($jdNoon - $baseJd);

        $stemIndex = (($diffDays % 10) + 10) % 10;
        $branchIndex = ((($diffDays + 10) % 12) + 12) % 12;

        return $this->pillarFrom($stemIndex, $branchIndex);
    }

    private function pillarFrom(int $stemIndex, int $branchIndex): array
    {
        $s = self::STEMS[$stemIndex];
        $b = self::BRANCHES[$branchIndex];

        return [
            'stem' => $s['k'], 'stemHanja' => $s['h'], 'stemElement' => $s['el'], 'stemYinYang' => $s['yy'],
            'branch' => $b['k'], 'branchHanja' => $b['h'], 'branchElement' => $b['el'], 'branchYinYang' => $b['yy'],
            'animal' => $b['animal'], 'label' => $s['k'].$b['k'], 'hanja' => $s['h'].$b['h'],
        ];
    }

    /**
     * app.js의 toJD()와 동일한 율리우스일 공식(Fliegel & Van Flandern).
     */
    private function toJd(int $y, int $m, int $d, float $hUt): float
    {
        if ($m <= 2) {
            $y -= 1;
            $m += 12;
        }

        $a = (int) floor($y / 100);
        $b = 2 - $a + (int) floor($a / 4);

        return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5 + $hUt / 24;
    }

    /**
     * app.js의 birthToUT()를 그대로 옮김: 생일 시각(KST 가정, UTC+9)을 경도 보정까지
     * 반영해 UT로 바꾸고, 그 결과 자정을 넘나들면(guard는 두 while 루프가 공유 —
     * 원본과 동일하게 리셋하지 않음) 날짜를 하루 밀어서 정규화한다.
     *
     * @return array{y: int, m: int, d: int, utHour: float}
     */
    private function birthToUt(int $year, int $month, int $day, int $hour, int $minute, float $longitude): array
    {
        $utHour = $hour + $minute / 60 - 9;
        $y = $year;
        $m = $month;
        $d = $day;

        $lonCorrectionMin = ($longitude - 135) * 4;
        $utHour += $lonCorrectionMin / 60;

        $guard = 0;
        while ($utHour < 0 && $guard < 10) {
            $utHour += 24;
            $d -= 1;
            $guard++;
        }
        while ($utHour >= 24 && $guard < 10) {
            $utHour -= 24;
            $d += 1;
            $guard++;
        }

        // JS의 new Date(Date.UTC(y, m-1, d))와 동일한 달력 롤오버 정규화(d가 0 이하이거나
        // 그 달의 일수를 넘어가도 올바른 실제 날짜로 보정됨).
        $base = new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $y, $m), new DateTimeZone('UTC'));
        $adjusted = $base->modify(sprintf('%+d days', $d - 1));

        return [
            'y' => (int) $adjusted->format('Y'),
            'm' => (int) $adjusted->format('n'),
            'd' => (int) $adjusted->format('j'),
            'utHour' => $utHour,
        ];
    }
}
