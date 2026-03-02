<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Delete upload files older than N days from storage/app/uploads/.
 *
 * Useful for purging orphaned or expired temporary uploads.
 */
class KolabriCleanupUploadsCommand extends Command
{
    protected $signature = 'kolabri:cleanup:uploads
        {--days=90  : Delete files older than this many days}
        {--dry-run  : List files without deleting them}';

    protected $description = 'Delete old files from storage/app/uploads/';

    public function handle(): int
    {
        $days   = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = time() - ($days * 86400);

        $dir = storage_path('app/uploads');

        if (! is_dir($dir)) {
            $this->line("Upload directory not found: {$dir}");
            return self::SUCCESS;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        $deleted     = 0;
        $deletedSize = 0;
        $listed      = 0;

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (! $file->isFile()) {
                continue;
            }

            if ($file->getMTime() >= $cutoff) {
                continue;
            }

            $path = $file->getRealPath();
            $size = $file->getSize();

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] Would delete: %s  (%s KB, %s)',
                    $path,
                    round($size / 1024, 1),
                    date('d.m.Y', $file->getMTime())
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
            $this->info("Dry-run: {$listed} file(s) would be deleted (older than {$days} days).");
        } else {
            $kb = round($deletedSize / 1024, 1);
            $this->info("Deleted {$deleted} file(s), freed {$kb} KB (older than {$days} days).");
        }

        return self::SUCCESS;
    }
}
