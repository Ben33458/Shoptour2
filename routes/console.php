<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------------------------------------------------------------------------
// Backup schedule
// ---------------------------------------------------------------------------
// On Internetwerk shared hosting, activate cron via Plesk panel:
//   Command : php /httpdocs/kolabri/artisan schedule:run >> /dev/null 2>&1
//   Timing  : every minute  (cron expression: * * * * *)
// ---------------------------------------------------------------------------
Schedule::command('kolabri:backup:db')->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('kolabri:backup:files')->weeklyOn(0, '03:00') // Sundays 03:00
    ->withoutOverlapping()
    ->runInBackground();

// ---------------------------------------------------------------------------
// Deferred task runner (WP-18)
// ---------------------------------------------------------------------------
// Processes pending DB-backed tasks every 5 minutes.
// Replaces a persistent queue worker on shared hosting.
// ---------------------------------------------------------------------------
Schedule::command('kolabri:tasks:run')->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ---------------------------------------------------------------------------
// Housekeeping (WP-18)
// ---------------------------------------------------------------------------
Schedule::command('kolabri:cleanup:backups --keep=14')->dailyAt('02:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('kolabri:cleanup:uploads --days=90')->weeklyOn(1, '04:00') // Mondays 04:00
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('kolabri:cleanup:logs --days=180')->weeklyOn(1, '04:30') // Mondays 04:30
    ->withoutOverlapping()
    ->runInBackground();
