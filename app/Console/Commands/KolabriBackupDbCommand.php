<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Pure-PHP MySQL backup (no mysqldump required).
 *
 * Produces a gzip-compressed SQL dump in storage/app/backups/
 * and prunes old backups beyond the configured retention period.
 *
 * Usage:
 *   php artisan kolabri:backup:db
 *   php artisan kolabri:backup:db --keep=30
 *
 * Schedule (add to routes/console.php):
 *   Schedule::command('kolabri:backup:db')->dailyAt('02:00');
 */
class KolabriBackupDbCommand extends Command
{
    protected $signature = 'kolabri:backup:db
                            {--keep=       : Override BACKUP_KEEP retention (number of files).}
                            {--no-compress : Write plain .sql instead of .sql.gz}';

    protected $description = 'Create a PHP-native MySQL dump → storage/app/backups/db_<timestamp>.sql.gz';

    // Maximum bytes per file written to disk at once (chunked to avoid OOM)
    private const CHUNK_BYTES = 512 * 1024; // 512 KB

    // INSERT batch size (rows per INSERT statement)
    private const INSERT_BATCH = 500;

    public function handle(): int
    {
        $connection = DB::connection();
        $driver     = $connection->getDriverName();

        if ($driver !== 'mysql') {
            $this->error("kolabri:backup:db only supports MySQL (current: {$driver}).");
            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);

        $timestamp  = now()->format('Y-m-d_His');
        $compress   = ! $this->option('no-compress');
        $ext        = $compress ? 'sql.gz' : 'sql';
        $backupPath = "{$backupDir}/db_{$timestamp}.{$ext}";

        $this->info("Starting DB backup → {$backupPath}");

        $pdo = $connection->getPdo();

        // ── Build SQL ─────────────────────────────────────────────────────────

        $sql = $this->buildSql($pdo);

        // ── Write to disk ─────────────────────────────────────────────────────

        if ($compress) {
            file_put_contents($backupPath, gzencode($sql, 6));
        } else {
            file_put_contents($backupPath, $sql);
        }

        $sizeMb = round(filesize($backupPath) / 1024 / 1024, 2);
        $this->info("✓ Backup written  ({$sizeMb} MB)");

        // ── Prune old backups ─────────────────────────────────────────────────

        $keep = (int) ($this->option('keep') ?: config('kolabri.backup_keep', 14));
        $this->pruneOldBackups($backupDir, 'db_*.sql*', $keep);

        $this->info("Done. Keeping last {$keep} DB backups.");

        return self::SUCCESS;
    }

    // =========================================================================
    // SQL generation
    // =========================================================================

    private function buildSql(\PDO $pdo): string
    {
        $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

        $header  = "-- Kolabri DB Backup\n";
        $header .= "-- Database : {$dbName}\n";
        $header .= "-- Date     : " . now()->toDateTimeString() . "\n";
        $header .= "-- Generator: kolabri:backup:db (PHP-native)\n\n";
        $header .= "SET NAMES utf8mb4;\n";
        $header .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $footer  = "\nSET FOREIGN_KEY_CHECKS = 1;\n";

        $tables = $pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);

        $body = '';
        foreach ($tables as $table) {
            $body .= $this->dumpTable($pdo, (string) $table);
        }

        return $header . $body . $footer;
    }

    private function dumpTable(\PDO $pdo, string $table): string
    {
        $output = "-- --------------------------------------------------------\n";
        $output .= "-- Table `{$table}`\n";
        $output .= "-- --------------------------------------------------------\n\n";

        // CREATE TABLE statement
        $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_NUM);
        $output .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $output .= $row[1] . ";\n\n";

        // Row data
        $stmt = $pdo->query("SELECT * FROM `{$table}`");

        $columns = [];
        for ($i = 0; $i < $stmt->columnCount(); $i++) {
            $meta      = $stmt->getColumnMeta($i);
            $columns[] = $meta['name'];
        }

        if (empty($columns)) {
            return $output;
        }

        $colList = '`' . implode('`, `', $columns) . '`';
        $batch   = [];
        $count   = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
            $values = array_map(static function ($val) use ($pdo): string {
                if ($val === null) {
                    return 'NULL';
                }
                return $pdo->quote((string) $val);
            }, $row);

            $batch[] = '(' . implode(', ', $values) . ')';
            $count++;

            if (count($batch) >= self::INSERT_BATCH) {
                $output .= "INSERT INTO `{$table}` ({$colList}) VALUES\n"
                    . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $output .= "INSERT INTO `{$table}` ({$colList}) VALUES\n"
                . implode(",\n", $batch) . ";\n";
        }

        if ($count > 0) {
            $output .= "-- {$count} rows\n";
        }

        return $output . "\n";
    }

    // =========================================================================
    // Pruning
    // =========================================================================

    private function pruneOldBackups(string $dir, string $pattern, int $keep): void
    {
        $files = glob("{$dir}/{$pattern}");
        if ($files === false) {
            return;
        }

        // Sort oldest first
        usort($files, static fn ($a, $b) => filemtime($a) <=> filemtime($b));

        $toDelete = array_slice($files, 0, max(0, count($files) - $keep));
        foreach ($toDelete as $file) {
            @unlink($file);
            $this->line("  Pruned: " . basename($file));
        }
    }
}
