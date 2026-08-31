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
Schedule::command('fortune:generate-daily')->dailyAt('00:10');
Schedule::command('fortune:charge-subscriptions')->dailyAt('00:30');
