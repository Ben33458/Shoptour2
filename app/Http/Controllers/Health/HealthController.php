<?php

declare(strict_types=1);

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight health-probe endpoints for uptime monitoring tools.
 *
 * GET /health/db      — verify DB connectivity
 * GET /health/storage — verify storage paths are writable
 *
 * No authentication required; returns only non-sensitive status info.
 */
class HealthController extends Controller
{
    /**
     * Check database connectivity.
     *
     * Returns 200 + {"status":"ok"} on success.
     * Returns 503 + {"status":"error","message":"..."} on failure.
     */
    public function db(): JsonResponse
    {
        try {
            DB::statement('SELECT 1');

            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Check that key storage paths are writable.
     *
     * Returns 200 + {"status":"ok"} if all paths are writable.
     * Returns 503 + {"status":"error","path":"..."} on first unwritable path.
     */
    public function storage(): JsonResponse
    {
        $paths = [
            storage_path('app'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            if (! is_writable($path)) {
                return response()->json([
                    'status' => 'error',
                    'path'   => $path,
                ], 503);
            }
        }

        return response()->json(['status' => 'ok']);
    }
}
