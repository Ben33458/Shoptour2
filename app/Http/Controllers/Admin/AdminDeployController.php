<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Web-based deployment operations for Internetwerk shared hosting (no SSH).
 *
 * All endpoints require:
 *  1. Authenticated admin user (via existing 'admin' middleware).
 *  2. Valid CSRF token (via 'web' middleware).
 *  3. Correct DEPLOY_TOKEN from .env submitted in the request body.
 *
 * Available operations (POST):
 *  /admin/deploy/migrate  – run migrations (--force)
 *  /admin/deploy/cache    – rebuild config/route/view caches
 *  /admin/deploy/clear    – clear all caches (useful during rollback)
 *  /admin/deploy/backup   – trigger DB + file backups on-demand
 *
 * GET /admin/deploy shows the control panel form.
 */
class AdminDeployController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    // =========================================================================
    // index
    // =========================================================================

    /**
     * GET /admin/deploy
     * Show the deployment control panel.
     */
    public function index(): View
    {
        $tokenConfigured = ! empty(config('kolabri.deploy_token'));
        $manifest        = $this->readManifest();

        return view('admin.deploy.index', compact('tokenConfigured', 'manifest'));
    }

    // =========================================================================
    // migrate
    // =========================================================================

    /**
     * POST /admin/deploy/migrate
     * Run pending migrations.
     */
    public function migrate(Request $request): RedirectResponse
    {
        $this->validateDeployToken($request);

        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        $this->audit->log('deploy.migrate', meta: [
            'output' => mb_substr($output, 0, 2000),
            'ip'     => $request->ip(),
        ]);

        return redirect()->route('admin.deploy.index')
            ->with('deploy_output', $output)
            ->with('deploy_op', 'Migrate')
            ->with('success', 'Migrationen ausgeführt.');
    }

    // =========================================================================
    // cache
    // =========================================================================

    /**
     * POST /admin/deploy/cache
     * Rebuild config, route and view caches.
     */
    public function cache(Request $request): RedirectResponse
    {
        $this->validateDeployToken($request);

        $output = [];

        Artisan::call('config:cache');
        $output[] = '$ config:cache: ' . trim(Artisan::output());

        Artisan::call('route:cache');
        $output[] = '$ route:cache: ' . trim(Artisan::output());

        Artisan::call('view:cache');
        $output[] = '$ view:cache: ' . trim(Artisan::output());

        $combined = implode("\n", $output);

        $this->audit->log('deploy.cache', meta: ['ip' => $request->ip()]);

        return redirect()->route('admin.deploy.index')
            ->with('deploy_output', $combined)
            ->with('deploy_op', 'Cache rebuild')
            ->with('success', 'Caches neu aufgebaut.');
    }

    // =========================================================================
    // clear
    // =========================================================================

    /**
     * POST /admin/deploy/clear
     * Clear all caches (useful during rollback or config changes).
     */
    public function clear(Request $request): RedirectResponse
    {
        $this->validateDeployToken($request);

        $output = [];

        Artisan::call('cache:clear');
        $output[] = '$ cache:clear: ' . trim(Artisan::output());

        Artisan::call('config:clear');
        $output[] = '$ config:clear: ' . trim(Artisan::output());

        Artisan::call('route:clear');
        $output[] = '$ route:clear: ' . trim(Artisan::output());

        Artisan::call('view:clear');
        $output[] = '$ view:clear: ' . trim(Artisan::output());

        $this->audit->log('deploy.clear', meta: ['ip' => $request->ip()]);

        return redirect()->route('admin.deploy.index')
            ->with('deploy_output', implode("\n", $output))
            ->with('deploy_op', 'Cache clear')
            ->with('success', 'Alle Caches geleert.');
    }

    // =========================================================================
    // backup
    // =========================================================================

    /**
     * POST /admin/deploy/backup
     * Trigger DB + file backup on-demand.
     */
    public function backup(Request $request): RedirectResponse
    {
        $this->validateDeployToken($request);

        $output = [];

        Artisan::call('kolabri:backup:db');
        $output[] = '$ backup:db: ' . trim(Artisan::output());

        Artisan::call('kolabri:backup:files');
        $output[] = '$ backup:files: ' . trim(Artisan::output());

        $this->audit->log('deploy.backup', meta: ['ip' => $request->ip()]);

        return redirect()->route('admin.deploy.index')
            ->with('deploy_output', implode("\n", $output))
            ->with('deploy_op', 'On-demand backup')
            ->with('success', 'Backup erfolgreich erstellt.');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Validate the deploy_token field against the configured secret.
     * Aborts with 403 on mismatch or if DEPLOY_TOKEN is not configured.
     */
    private function validateDeployToken(Request $request): void
    {
        $configuredToken = (string) config('kolabri.deploy_token', '');

        if (empty($configuredToken)) {
            abort(503, 'Deployment endpoint not active. Set DEPLOY_TOKEN in .env.');
        }

        $provided = (string) $request->input('deploy_token', '');

        if (! hash_equals($configuredToken, $provided)) {
            abort(403, 'Ungültiger Deploy-Token.');
        }
    }

    /**
     * Read storage/app/release.json, return array or empty array.
     *
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
}
