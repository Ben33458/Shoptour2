<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\Driver\DriverSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/driver/sync
 *
 * Accepts a batch of driver events from the PWA offline queue and applies
 * them to the domain.
 *
 * Request body (JSON):
 * {
 *   "device_id": "uuid-of-device",
 *   "events": [
 *     {
 *       "client_event_id": "uuid",
 *       "event_type": "arrived",
 *       "tour_stop_id": 42,
 *       "payload": {}
 *     },
 *     ...
 *   ]
 * }
 *
 * Response 200 (always — individual failures are reported inside results):
 * {
 *   "applied": 3,
 *   "rejected": 0,
 *   "duplicates": 1,
 *   "results": [
 *     {"client_event_id": "uuid", "status": "applied",    "error": null},
 *     {"client_event_id": "uuid", "status": "duplicate",  "error": null},
 *     {"client_event_id": "uuid", "status": "rejected",   "error": "TourStop #5 not found."}
 *   ]
 * }
 */
class DriverSyncController extends Controller
{
    public function __construct(
        private readonly DriverSyncService $syncService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Validate only the fields we require to be present and well-formed.
        // We intentionally do NOT validate optional per-event fields (tour_stop_id,
        // order_item_id, payload, …) here — their presence and values are checked
        // in DriverSyncService per event type.
        //
        // IMPORTANT: use $request->input() for the data passed to the service, not
        // $validated. Laravel's validate() returns ONLY keys that have explicit rules,
        // which would silently strip tour_stop_id, payload, etc. from every event.
        $request->validate([
            'device_id'                => ['required', 'string', 'max:128'],
            'events'                   => ['required', 'array'],
            'events.*.client_event_id' => ['required', 'string', 'max:128'],
            'events.*.event_type'      => ['required', 'string', 'max:64'],
        ]);

        /** @var int|null $employeeId */
        $employeeId = $request->attributes->get('driver_employee_id');
        $deviceId   = (string) $request->input('device_id');
        $events     = (array)  $request->input('events', []);

        $result = $this->syncService->applyEvents($employeeId, $deviceId, $events);

        return response()->json($result);
    }
}
