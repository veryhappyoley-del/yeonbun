<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// (2026-08-31 신설) "오늘의 운세" 구독 — 이 프로젝트에 스케줄 작업이 생기는 첫 지점.
// config('app.timezone')이 Asia/Seoul이라 dailyAt()은 이미 KST 기준으로 동작한다.
// **배포 시 필요한 작업**: Laravel Cloud 대시보드에서 스케줄러(Cron)가 실제로
// 켜져 있는지 확인 — 지금까지 스케줄 작업이 하나도 없어서 처음 켜는 것.
//
// (보안 점검 대응, 2026-09) withoutOverlapping()/onOneServer() 추가 — 특히
// fortune:charge-subscriptions는 실제 카드 결제를 일으키는 커맨드라, 수동 재실행과
// 스케줄러 실행이 겹치거나 여러 서버에서 스케줄러가 동시에 도는 경우 같은 구독이 두
// 번 청구될 위험이 있었다. onOneServer()는 database 캐시 드라이버의 원자적 락
// (cache_locks 테이블, 0001_01_01_000001_create_cache_table.php에 이미 존재)을 쓴다.
Schedule::command('fortune:generate-daily')->dailyAt('00:10')->withoutOverlapping()->onOneServer();
Schedule::command('fortune:charge-subscriptions')->dailyAt('00:30')->withoutOverlapping()->onOneServer();
