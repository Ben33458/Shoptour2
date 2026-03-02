<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Artisan-side release helper.
 *
 * Writes the release manifest (storage/app/release.json) and optionally
 * creates a release zip.  Vendor/ must already be production-built by the
 * caller (scripts/release.ps1) before invoking this command.
 *
 * Usage (called automatically by scripts/release.ps1):
 *   php artisan kolabri:release:build --version=1.2.3
 *
 * Usage (standalone, e.g. on CI):
 *   php artisan kolabri:release:build --version=1.2.3 --zip
 */
class KolabriReleaseBuildCommand extends Command
{
    protected $signature = 'kolabri:release:build
                            {--version=    : Semantic version (e.g. 1.2.3). Required.}
                            {--git-sha=    : Git SHA to embed. Auto-detected if omitted.}
                            {--zip         : Also create a release zip in ./releases/}
                            {--env=production : Target environment label for the manifest.}';

    protected $description = 'Write the release manifest (and optionally a release zip).';

    // Directories/files included when --zip is used
    private const ZIP_DIRS  = ['app', 'bootstrap', 'config', 'database', 'lang',
                                'public', 'resources', 'routes', 'storage', 'vendor'];
    private const ZIP_FILES = ['artisan', 'composer.json', 'composer.lock', '.env.production.example'];

    public function handle(): int
    {
        $version = (string) $this->option('version');

        if (empty($version)) {
            $this->error('--version is required. Example: --version=1.2.3');
            return self::FAILURE;
        }

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->error("Version must be semver: MAJOR.MINOR.PATCH  (got: {$version})");
            return self::FAILURE;
        }

        // ── Git SHA ───────────────────────────────────────────────────────────

        $gitSha = (string) $this->option('git-sha');
        if (empty($gitSha)) {
            $gitSha = trim((string) shell_exec('git rev-parse --short HEAD 2>/dev/null')) ?: 'unknown';
        }

        // ── Write manifest ────────────────────────────────────────────────────

        $manifest = [
            'version'    => $version,
            'git_sha'    => $gitSha,
            'env'        => $this->option('env'),
            'build_date' => now()->toIso8601String(),
            'built_by'   => get_current_user() ?: php_uname('n'),
            'php_min'    => '8.2',
        ];

        $manifestPath = storage_path('app/release.json');
        File::ensureDirectoryExists(dirname($manifestPath));
        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->info("✓ Manifest written → {$manifestPath}");

        // ── Optional zip ─────────────────────────────────────────────────────

        if ($this->option('zip')) {
            $result = $this->createZip($version);
            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        $this->info("Release v{$version} ({$gitSha}) ready.");

        return self::SUCCESS;
    }

    // =========================================================================

    private function createZip(string $version): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('ZipArchive extension is not available. Install ext-zip.');
            return self::FAILURE;
        }

        $releasesDir = base_path('releases');
        File::ensureDirectoryExists($releasesDir);

        $timestamp = now()->format('Ymd-His');
        $zipPath   = "{$releasesDir}/kolabri-v{$version}-{$timestamp}.zip";

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Cannot create zip: {$zipPath}");
            return self::FAILURE;
        }

        $base = base_path();
        $added = 0;

        foreach (self::ZIP_DIRS as $dir) {
            $path = $base . DIRECTORY_SEPARATOR . $dir;
            if (! is_dir($path)) {
                continue;
            }
            $this->addDirToZip($zip, $path, $dir, $added);
        }

        foreach (self::ZIP_FILES as $file) {
            $path = $base . DIRECTORY_SEPARATOR . $file;
            if (file_exists($path)) {
                $zip->addFile($path, $file);
                $added++;
            }
        }

        $zip->close();

        $sizeMb = round(filesize($zipPath) / 1024 / 1024, 1);
        $this->info("✓ Zip created → {$zipPath}  ({$sizeMb} MB, {$added} entries)");

        return self::SUCCESS;
    }

    private function addDirToZip(ZipArchive $zip, string $dirPath, string $zipPrefix, int &$added): void
    {
        $skip = ['.git', '.env', 'node_modules', '.phpunit.cache', '.phpunit.result.cache'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            // Skip hidden files and dev artifacts
            $basename = $item->getFilename();
            if (in_array($basename, $skip, true) || str_starts_with($basename, '.env')) {
                continue;
            }

            $relativePath = $zipPrefix . DIRECTORY_SEPARATOR
                . substr($item->getPathname(), strlen($dirPath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($item->getPathname(), $relativePath);
                $added++;
            }
        }
    }
}
