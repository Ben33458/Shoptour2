<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin\DeferredTask;
use App\Models\System\SyncRun;
use App\Services\Wawi\DynamicSyncService;
use App\Services\Wawi\WawiSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    public function __construct(
        private readonly WawiSyncService   $syncService,
        private readonly DynamicSyncService $dynamicSync,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity'    => 'required|string|max:100',
            'records'   => 'present|array',
            'records.*' => 'array',
            'count'     => 'required|integer|min:0',
            'timestamp' => 'required|string',
        ]);

        $entity    = $validated['entity'];
        $records   = $validated['records'];
        $syncStart = now();
        $error     = null;
        $received  = 0;
        $tableName = null;

        try {
            if ($this->syncService->supports($entity)) {
                // ── Legacy handler: known entities with whitelisted columns ──────
                $received  = $this->syncService->sync($entity, $records);
                $tableName = $entity;  // WawiSyncService uses short names

                if ($entity === 'artikel') {
                    DeferredTask::create([
                        'type'         => 'wawi.sync_prices',
                        'payload_json' => '{}',
                        'status'       => DeferredTask::STATUS_PENDING,
                        'attempts'     => 0,
                        'max_attempts' => 3,
                    ]);
                }

                if ($entity === 'artikel_attribute') {
                    DeferredTask::create([
                        'type'         => 'wawi.sync_leergut',
                        'payload_json' => '{}',
                        'status'       => DeferredTask::STATUS_PENDING,
                        'attempts'     => 0,
                        'max_attempts' => 3,
                    ]);
                }
            } else {
                // ── Dynamic handler: auto-creates/extends wawi_* tables ──────────
                $tableName = $this->dynamicSync->tableNameFor($entity);
                $received  = $this->dynamicSync->upsert($entity, $records);
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('WaWi sync failed', [
                'entity' => $entity,
                'error'  => $error,
                'ip'     => $request->ip(),
            ]);
            // Return 200 so the PHP sync script continues with the next entity
            return response()->json([
                'status' => 'error',
                'entity' => $entity,
                'error'  => $error,
            ]);
        }

        // ── Persist sync log ────────────────────────────────────────────────────
        SyncRun::create([
            'source'            => 'wawi',
            'entity'            => $entity,
            'status'            => 'completed',
            'records_processed' => $received,
            'triggered_by'      => $request->ip(),
            'started_at'        => $syncStart,
            'finished_at'       => now(),
        ]);

        DB::table('wawi_sync_log')->insert([
            'entity'            => $entity,
            'table_name'        => $tableName ?? $entity,
            'records_received'  => count($records),
            'records_upserted'  => $received,
            'ip'                => $request->ip(),
            'created_at'        => now(),
        ]);

        // ── Update sync state cache (fast lookup for GET /api/sync/state) ───
        DB::table('wawi_sync_state')->upsert(
            [[
                'entity'      => $entity,
                'last_ts'     => now()->toDateTimeString(),
                'last_count'  => $received,
                'updated_at'  => now()->toDateTimeString(),
            ]],
            ['entity'],
            ['last_ts', 'last_count', 'updated_at'],
        );

        Log::info('WaWi sync received', [
            'entity'    => $entity,
            'count'     => $validated['count'],
            'received'  => $received,
            'ip'        => $request->ip(),
        ]);

        return response()->json([
            'status'    => 'ok',
            'entity'    => $entity,
            'table'     => $tableName ?? $entity,
            'upserted'  => $received,
        ]);
    }
}
