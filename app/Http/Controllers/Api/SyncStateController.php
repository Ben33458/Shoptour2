<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/sync/state
 *
 * Returns the last sync timestamp per WaWi entity.
 * Reads from the lightweight wawi_sync_state table (written by POST /api/sync).
 * Response time: < 100 ms.
 */
class SyncStateController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $rows = DB::table('wawi_sync_state')
            ->select('entity', 'last_ts')
            ->orderBy('entity')
            ->get();

        // Build { "dbo.tArtikel": "2026-04-20 12:00:00", ... }
        $result = $rows->pluck('last_ts', 'entity');

        return response()->json($result->isEmpty() ? (object) [] : $result);
    }
}
