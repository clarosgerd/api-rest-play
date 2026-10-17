<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');

// Full sync: football-data.org (current seasons) + ESPN every 4 hours
Schedule::command('sync:all')->cron('0 */4 * * *');

// Live scores: ESPN fixtures only, hourly (skip logic limits to every 2h effectively)
Schedule::command('espn:sync --type=fixtures')->hourly();
