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

Schedule::command('employee:auto-close')->hourly()->withoutOverlapping();

// ---------------------------------------------------------------------------
// Lexoffice — automatische Synchronisation
// ---------------------------------------------------------------------------
// Kontakte + Belege: stündlich (dauert ca. 3–10 min je nach Datenmenge)
Schedule::command('lexoffice:sync')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Referenzdaten (Artikel, Kategorien etc.): täglich nachts
Schedule::command('lexoffice:sync --full')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground();

// Zahlungen: alle 5 Minuten, je 100 Belege (~60 s).
// Leert den Initialrückstand in ca. 1 Tag; danach nur neue paid-Belege.
Schedule::command('lexoffice:import-payments --batch=100')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ---------------------------------------------------------------------------
// POS statistics — incremental refresh of stats_pos_daily
// ---------------------------------------------------------------------------
// Re-aggregates the last 3 days nightly to catch any late-arriving POS data.
// ---------------------------------------------------------------------------
Schedule::command('stats:refresh-pos --days=3')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground();

// ---------------------------------------------------------------------------
// Sync-Runs Cleanup (älter als 60 Tage)
// ---------------------------------------------------------------------------
Schedule::call(function () {
    \App\Models\System\SyncRun::where('created_at', '<', now()->subDays(60))->delete();
})->dailyAt('03:30');
