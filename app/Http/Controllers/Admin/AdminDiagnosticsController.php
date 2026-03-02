<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DeferredTask;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * GET /admin/diagnostics
 *
 * Admin-only page showing live application health:
 *  - App version (from storage/app/release.json)
 *  - DB connection status
 *  - Storage / cache writable flags
 *  - Session + cache driver info
 *  - PHP version
 *  - APP_DEBUG / APP_ENV safety checks
 *  - DEPLOY_TOKEN configured flag
 *  - Backup inventory (latest files)
 */
class AdminDiagnosticsController extends Controller
{
    public function index(): View
    {
        // ── App version ───────────────────────────────────────────────────────
        $manifest = $this->readManifest();

        // ── Database ──────────────────────────────────────────────────────────
        $dbOk    = false;
        $dbError = null;
        $dbName  = null;
        try {
            DB::connection()->getPdo();
            $dbOk   = true;
            $dbName = DB::connection()->getDatabaseName();
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        // ── Storage writability ───────────────────────────────────────────────
        $storageWritable = is_writable(storage_path('app'));
        $cacheWritable   = is_writable(base_path('bootstrap/cache'));
        $logsWritable    = is_writable(storage_path('logs'));

        // ── Session / cache ───────────────────────────────────────────────────
        $sessionDriver = config('session.driver', '?');
        $cacheDriver   = config('cache.default', '?');
        $queueConn     = config('queue.default', '?');

        // ── Security checks ───────────────────────────────────────────────────
        $debugOff         = ! config('app.debug');
        $envProduction    = config('app.env') === 'production';
        $deployTokenSet   = ! empty(config('kolabri.deploy_token'));
        $sessionSecure    = config('session.secure', false);

        // ── PHP ───────────────────────────────────────────────────────────────
        $phpVersion    = PHP_VERSION;
        $phpOk         = version_compare(PHP_VERSION, '8.2.0', '>=');
        $extensions    = [
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring'  => extension_loaded('mbstring'),
            'openssl'   => extension_loaded('openssl'),
            'fileinfo'  => extension_loaded('fileinfo'),
            'zip'       => extension_loaded('zip'),
            'gd'        => extension_loaded('gd'),
            'zlib'      => extension_loaded('zlib'),
        ];

        // ── Backups ───────────────────────────────────────────────────────────
        $latestDbBackup    = $this->latestBackup('db_*.sql*');
        $latestFilesBackup = $this->latestBackup('files_*.zip');
        $backupCount       = count(glob(storage_path('app/backups/*')) ?: []);

        // ── Disk usage ────────────────────────────────────────────────────────
        $storageFreeGb = round(disk_free_space(storage_path()) / 1024 / 1024 / 1024, 1);
        $storageTotalGb = round(disk_total_space(storage_path()) / 1024 / 1024 / 1024, 1);

        // ── Deferred tasks (WP-18) ────────────────────────────────────────────
        $pendingTasks = DeferredTask::where('status', DeferredTask::STATUS_PENDING)->count();
        $failedTasks  = DeferredTask::where('status', DeferredTask::STATUS_FAILED)->count();
        $recentErrors = DeferredTask::where('status', DeferredTask::STATUS_FAILED)
            ->latest()
            ->limit(5)
            ->get(['id', 'type', 'last_error', 'updated_at']);

        return view('admin.diagnostics.index', compact(
            'manifest',
            'dbOk', 'dbError', 'dbName',
            'storageWritable', 'cacheWritable', 'logsWritable',
            'sessionDriver', 'cacheDriver', 'queueConn',
            'debugOff', 'envProduction', 'deployTokenSet', 'sessionSecure',
            'phpVersion', 'phpOk', 'extensions',
            'latestDbBackup', 'latestFilesBackup', 'backupCount',
            'storageFreeGb', 'storageTotalGb',
            'pendingTasks', 'failedTasks', 'recentErrors',
        ));
    }

    // =========================================================================

    /**
     * @return array<string, mixed>
     */
    private function readManifest(): array
    {
        $path = storage_path('app/release.json');
        if (! file_exists($path)) {
            return [];
        }
        return json_decode((string) file_get_contents($path), true) ?? [];
    }

    /**
     * @return array{name: string, size_kb: int, mtime: string}|null
     */
    private function latestBackup(string $pattern): ?array
    {
        $files = glob(storage_path("app/backups/{$pattern}")) ?: [];
        if (empty($files)) {
            return null;
        }

        usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $latest = $files[0];

        return [
            'name'    => basename($latest),
            'size_kb' => (int) round(filesize($latest) / 1024),
            'mtime'   => date('d.m.Y H:i', (int) filemtime($latest)),
        ];
    }
}
