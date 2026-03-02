<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Delete log files older than N days from storage/logs/.
 *
 * The current laravel.log is always preserved regardless of age.
 * Rotated logs (laravel-YYYY-MM-DD.log, etc.) older than --days are removed.
 */
class KolabriCleanupLogsCommand extends Command
{
    protected $signature = 'kolabri:cleanup:logs
        {--days=180 : Delete log files older than this many days}
        {--dry-run  : List files without deleting them}';

    protected $description = 'Delete old log files from storage/logs/';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = time() - ($days * 86400);

        $dir = storage_path('logs');

        if (! is_dir($dir)) {
            $this->line("Logs directory not found: {$dir}");
            return self::SUCCESS;
        }

        // Always keep the current laravel.log
        $keep = realpath($dir . '/laravel.log') ?: '';

        $files = glob($dir . '/*.log') ?: [];

        $deleted     = 0;
        $deletedSize = 0;
        $listed      = 0;

        foreach ($files as $path) {
            $realPath = realpath($path) ?: $path;

            // Never delete the current laravel.log
            if ($realPath === $keep) {
                continue;
            }

            $mtime = filemtime($path);

            if ($mtime === false || $mtime >= $cutoff) {
                continue;
            }

            $size = filesize($path) ?: 0;

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] Would delete: %s  (%s KB, %s)',
                    $path,
                    round($size / 1024, 1),
                    date('d.m.Y', $mtime)
                ));
                $listed++;
            } else {
                if (@unlink($path)) {
                    $deleted++;
                    $deletedSize += $size;
                } else {
                    $this->warn("  Could not delete: {$path}");
                }
            }
        }

        if ($dryRun) {
            $this->info("Dry-run: {$listed} log file(s) would be deleted (older than {$days} days).");
        } else {
            $kb = round($deletedSize / 1024, 1);
            $this->info("Deleted {$deleted} log file(s), freed {$kb} KB (older than {$days} days).");
        }

        return self::SUCCESS;
    }
}
