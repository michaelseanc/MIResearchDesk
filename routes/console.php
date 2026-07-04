<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly TRACER refresh (TRACER regenerates its bulk files weekly). Pulls current-year
// contributions for every active tenant using its saved finance filter. Requires the scheduler
// (`php artisan schedule:work` locally, or a Cloudways cron/worker in production).
Schedule::command('finance:import-tracer --all --type=contributions')
    ->weeklyOn(1, '06:00')
    ->withoutOverlapping();

