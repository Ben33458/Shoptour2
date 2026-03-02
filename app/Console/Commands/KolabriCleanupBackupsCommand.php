<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Prune old backup files, keeping only the N newest of each type.
 *
 * Prunes both DB backups  (storage/app/backups/db_*.sql*)
 *          and file backups (storage/app/backups/files_*.zip)
 * independently.
 */
class KolabriCleanupBackupsCommand extends Command
{
    protected $signature = 'kolabri:cleanup:backups
        {--keep=14  : Number of most-recent backups to keep per type}
        {--dry-run  : List files to be deleted without actually deleting them}';

    protected $description = 'Prune old backup files, keeping only the N newest per type';

    public function handle(): int
    {
        $keep   = max(1, (int) $this->option('keep'));
        $dryRun = (bool) $this->option('dry-run');

        $backupDir = storage_path('app/backups');

        $this->prunePattern($backupDir, 'db_*.sql*',    'DB backups',   $keep, $dryRun);
        $this->prunePattern($backupDir, 'files_*.zip',  'File backups', $keep, $dryRun);

        return self::SUCCESS;
    }

    private function prunePattern(
        string $dir,
        string $pattern,
        string $label,
        int    $keep,
        bool   $dryRun
    ): void {
        $files = glob("{$dir}/{$pattern}") ?: [];

        if (empty($files)) {
            $this->line("  {$label}: none found.");
            return;
        }

        // Sort newest-first by modification time
        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));

        $toDelete = array_slice($files, $keep);

        if (empty($toDelete)) {
            $this->line("  {$label}: {$keep} or fewer found, nothing to prune.");
            return;
        }

        $deletedCount = 0;
        $deletedSize  = 0;

        foreach ($toDelete as $path) {
            $size = filesize($path) ?: 0;

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] Would delete: %s  (%s KB, %s)',
                    basename($path),
                    round($size / 1024, 1),
                    date('d.m.Y', (int) filemtime($path))
                ));
                $deletedCount++;
                $deletedSize += $size;
            } else {
                if (@unlink($path)) {
                    $deletedCount++;
                    $deletedSize += $size;
                } else {
                    $this->warn("  Could not delete: {$path}");
                }
            }
        }

        $verb = $dryRun ? 'Would delete' : 'Deleted';
        $kb   = round($deletedSize / 1024, 1);
        $this->info("  {$label}: {$verb} {$deletedCount} file(s), {$kb} KB freed (keeping {$keep} newest).");
    }
}
