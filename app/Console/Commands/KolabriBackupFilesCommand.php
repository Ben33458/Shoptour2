<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Zip critical storage/app sub-directories into a backup archive.
 *
 * Targets:
 *   storage/app/invoices/       – finalized PDF invoices
 *   storage/app/driver_uploads/ – proof-of-delivery photos/PDFs
 *   storage/app/exports/        – CSV/Lexoffice exports
 *
 * Output: storage/app/backups/files_<timestamp>.zip
 * Old archives are pruned beyond BACKUP_KEEP retention.
 *
 * Usage:
 *   php artisan kolabri:backup:files
 *   php artisan kolabri:backup:files --keep=30
 *
 * Schedule (routes/console.php):
 *   Schedule::command('kolabri:backup:files')->weeklyOn(0, '03:00');
 */
class KolabriBackupFilesCommand extends Command
{
    protected $signature = 'kolabri:backup:files
                            {--keep= : Override BACKUP_KEEP retention (number of archives).}';

    protected $description = 'Zip storage/app critical directories → storage/app/backups/files_<timestamp>.zip';

    /** Directories under storage/app/ to include in the archive. */
    private const BACKUP_DIRS = [
        'invoices',
        'driver_uploads',
        'exports',
    ];

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('ext-zip is required for kolabri:backup:files. Install php-zip.');
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $timestamp  = now()->format('Y-m-d_His');
        $backupPath = "{$backupDir}/files_{$timestamp}.zip";

        $this->info("Starting file backup → {$backupPath}");

        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot create zip: {$backupPath}");
            return self::FAILURE;
        }

        $totalFiles = 0;

        foreach (self::BACKUP_DIRS as $dirName) {
            $dirPath = storage_path("app/{$dirName}");

            if (! is_dir($dirPath)) {
                $this->line("  Skipped (not found): storage/app/{$dirName}");
                continue;
            }

            $added = $this->addDirToZip($zip, $dirPath, $dirName);
            $totalFiles += $added;
            $this->line("  Added storage/app/{$dirName}  ({$added} files)");
        }

        $zip->close();

        if ($totalFiles === 0) {
            // Remove empty zip
            @unlink($backupPath);
            $this->warn('No files found in any backup directory – archive not created.');
            return self::SUCCESS;
        }

        $sizeMb = round(filesize($backupPath) / 1024 / 1024, 2);
        $this->info("✓ Archive written: {$backupPath}  ({$sizeMb} MB, {$totalFiles} files)");

        // ── Prune old archives ────────────────────────────────────────────────

        $keep = (int) ($this->option('keep') ?: config('kolabri.backup_keep', 14));
        $this->pruneOldBackups($backupDir, 'files_*.zip', $keep);
        $this->info("Done. Keeping last {$keep} file backups.");

        return self::SUCCESS;
    }

    // =========================================================================

    private function addDirToZip(ZipArchive $zip, string $dirPath, string $zipPrefix): int
    {
        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = $zipPrefix . '/'
                . str_replace('\\', '/', substr($item->getPathname(), strlen($dirPath) + 1));

            if ($item->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($item->getPathname(), $relative);
                $count++;
            }
        }

        return $count;
    }

    private function pruneOldBackups(string $dir, string $pattern, int $keep): void
    {
        $files = glob("{$dir}/{$pattern}");
        if ($files === false) {
            return;
        }

        usort($files, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        $toDelete = array_slice($files, 0, max(0, count($files) - $keep));
        foreach ($toDelete as $file) {
            @unlink($file);
            $this->line('  Pruned: ' . basename($file));
        }
    }
}
