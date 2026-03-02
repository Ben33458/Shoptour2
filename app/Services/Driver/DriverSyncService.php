<?php

declare(strict_types=1);

namespace App\Services\Driver;

use App\Models\Catalog\Product;
use App\Models\Delivery\FulfillmentEvent;
use App\Models\Delivery\TourStop;
use App\Models\Driver\DriverEvent;
use App\Models\Driver\DriverUpload;
use App\Models\Orders\OrderItem;
use App\Services\Delivery\FulfillmentService;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Processes a batch of driver events sent from the PWA offline queue.
 *
 * Key design goals:
 *   - Idempotent:    if (device_id, client_event_id) already exists → "duplicate", skip, no error.
 *   - Transactional: each event is persisted + applied in its own DB::transaction().
 *   - Non-fatal:     a single failing event does NOT abort the whole batch.
 *
 * Return shape of applyEvents():
 * [
 *   'applied'    => int,   // events that were applied to domain
 *   'rejected'   => int,   // events that failed validation or domain logic
 *   'duplicates' => int,   // events already processed (idempotency hit)
 *   'results'    => [      // one entry per input event, same order
 *     ['client_event_id' => '...', 'status' => 'applied|rejected|duplicate', 'error' => '...'],
 *     ...
 *   ],
 * ]
 */
class DriverSyncService
{
    public function __construct(
        private readonly FulfillmentService $fulfillmentService,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Apply a batch of driver events.
     *
     * @param  int|null $employeeId   From bearer token (may be null if Employee module absent)
     * @param  string   $deviceId     Stable device identifier (UUID set by PWA)
     * @param  array<int, array<string, mixed>> $events
     * @return array{applied: int, rejected: int, duplicates: int, results: list<array<string, mixed>>}
     */
    public function applyEvents(?int $employeeId, string $deviceId, array $events): array
    {
        $applied    = 0;
        $rejected   = 0;
        $duplicates = 0;
        $results    = [];

        foreach ($events as $eventData) {
            $clientEventId = (string) ($eventData['client_event_id'] ?? '');

            // Guard: client_event_id is mandatory
            if ($clientEventId === '') {
                $rejected++;
                $results[] = [
                    'client_event_id' => '',
                    'status'          => 'rejected',
                    'error'           => 'Missing client_event_id.',
                ];
                continue;
            }

            // Idempotency check — a unique constraint on (device_id, client_event_id)
            // guarantees correctness under concurrent syncs, but we also check here
            // to give a friendly "duplicate" status rather than a DB error.
            if ($this->eventExists($deviceId, $clientEventId)) {
                $duplicates++;
                $results[] = [
                    'client_event_id' => $clientEventId,
                    'status'          => 'duplicate',
                    'error'           => null,
                ];
                continue;
            }

            [$status, $error] = $this->processSingleEvent(
                employeeId:    $employeeId,
                deviceId:      $deviceId,
                clientEventId: $clientEventId,
                eventData:     $eventData,
            );

            if ($status === DriverEvent::STATUS_APPLIED) {
                $applied++;
            } else {
                $rejected++;
            }

            $results[] = [
                'client_event_id' => $clientEventId,
                'status'          => $status,
                'error'           => $error,
            ];
        }

        return compact('applied', 'rejected', 'duplicates', 'results');
    }

    // =========================================================================
    // Single-event processing
    // =========================================================================

    /**
     * Persist and apply one event inside a DB transaction.
     *
     * Returns [apply_status, error_message|null].
     *
     * @param  array<string, mixed> $eventData
     * @return array{0: string, 1: string|null}
     */
    private function processSingleEvent(
        ?int   $employeeId,
        string $deviceId,
        string $clientEventId,
        array  $eventData,
    ): array {
        $eventType = (string) ($eventData['event_type'] ?? '');
        $now       = now();

        $driverEvent = null;

        try {
            DB::transaction(function () use (
                $employeeId,
                $deviceId,
                $clientEventId,
                $eventData,
                $eventType,
                $now,
                &$driverEvent,
            ): void {
                // 1. Persist the raw event first
                $driverEvent = DriverEvent::create([
                    'employee_id'     => $employeeId,
                    'device_id'       => $deviceId,
                    'client_event_id' => $clientEventId,
                    'event_type'      => $eventType,
                    'tour_id'         => isset($eventData['tour_id'])      ? (int) $eventData['tour_id']      : null,
                    'tour_stop_id'    => isset($eventData['tour_stop_id']) ? (int) $eventData['tour_stop_id'] : null,
                    'order_id'        => isset($eventData['order_id'])     ? (int) $eventData['order_id']     : null,
                    'order_item_id'   => isset($eventData['order_item_id'])? (int) $eventData['order_item_id']: null,
                    'payload_json'    => $eventData['payload'] ?? $eventData['payload_json'] ?? null,
                    'received_at'     => $now,
                    'apply_status'    => DriverEvent::STATUS_PENDING,
                    'apply_error'     => null,
                    'applied_at'      => null,
                ]);

                // 2. Apply domain logic
                $this->dispatchEvent($driverEvent, $eventData);

                // 3. Mark applied
                $driverEvent->update([
                    'apply_status' => DriverEvent::STATUS_APPLIED,
                    'applied_at'   => $now,
                ]);
            });

            return [DriverEvent::STATUS_APPLIED, null];
        } catch (Throwable $e) {
            // Transaction rolled back; persist rejected record outside the transaction
            $errorMessage = $e->getMessage();

            try {
                DriverEvent::create([
                    'employee_id'     => $employeeId,
                    'device_id'       => $deviceId,
                    'client_event_id' => $clientEventId,
                    'event_type'      => $eventType,
                    'tour_id'         => isset($eventData['tour_id'])      ? (int) $eventData['tour_id']      : null,
                    'tour_stop_id'    => isset($eventData['tour_stop_id']) ? (int) $eventData['tour_stop_id'] : null,
                    'order_id'        => isset($eventData['order_id'])     ? (int) $eventData['order_id']     : null,
                    'order_item_id'   => isset($eventData['order_item_id'])? (int) $eventData['order_item_id']: null,
                    'payload_json'    => $eventData['payload'] ?? $eventData['payload_json'] ?? null,
                    'received_at'     => $now,
                    'apply_status'    => DriverEvent::STATUS_REJECTED,
                    'apply_error'     => mb_substr($errorMessage, 0, 1000),
                    'applied_at'      => null,
                ]);
            } catch (Throwable) {
                // If persisting the rejected record also fails (e.g. duplicate key
                // due to race with a concurrent sync), swallow — we already decided
                // outcome above.
            }

            return [DriverEvent::STATUS_REJECTED, $errorMessage];
        }
    }

    // =========================================================================
    // Domain dispatch
    // =========================================================================

    /**
     * Route a validated DriverEvent to the appropriate domain service method.
     *
     * @param  array<string, mixed> $eventData  Raw payload from the client
     * @throws \RuntimeException|\InvalidArgumentException on domain errors
     * @throws \InvalidArgumentException                  on missing required fields
     */
    private function dispatchEvent(DriverEvent $driverEvent, array $eventData): void
    {
        match ($driverEvent->event_type) {
            DriverEvent::TYPE_ARRIVED              => $this->handleArrived($driverEvent),
            DriverEvent::TYPE_FINISHED             => $this->handleFinished($driverEvent),
            DriverEvent::TYPE_ITEM_DELIVERED       => $this->handleItemDelivered($driverEvent),
            DriverEvent::TYPE_ITEM_NOT_DELIVERED   => $this->handleItemNotDelivered($driverEvent),
            DriverEvent::TYPE_PAYMENT              => $this->handlePayment($driverEvent),
            DriverEvent::TYPE_EMPTIES_ADJUSTMENT   => $this->handleEmptiesAdjustment($driverEvent),
            DriverEvent::TYPE_BREAKAGE_ADJUSTMENT  => $this->handleBreakageAdjustment($driverEvent),
            DriverEvent::TYPE_NOTE                 => $this->handleNote($driverEvent),
            DriverEvent::TYPE_UPLOAD_REQUESTED     => $this->handleUploadRequested($driverEvent, $eventData),
            DriverEvent::TYPE_UPLOAD               => $this->handleUpload($driverEvent),
            default => throw new \InvalidArgumentException(
                "Unknown event_type: '{$driverEvent->event_type}'."
            ),
        };
    }

    // =========================================================================
    // Per-type handlers
    // =========================================================================

    private function handleArrived(DriverEvent $event): void
    {
        $stop = $this->requireTourStop($event);
        $this->fulfillmentService->markArrived($stop, $event->employee_id);
    }

    private function handleFinished(DriverEvent $event): void
    {
        $stop = $this->requireTourStop($event);
        $this->fulfillmentService->markFinished($stop, $event->employee_id);
    }

    private function handleItemDelivered(DriverEvent $event): void
    {
        $stop = $this->requireTourStop($event);
        $item = $this->requireOrderItem($event);

        $qty = (int) ($event->payload_json['qty'] ?? 0);

        if ($qty <= 0) {
            throw new \InvalidArgumentException('item_delivered: payload.qty must be > 0.');
        }

        $this->fulfillmentService->recordItemDelivery($stop, $item, $qty, $event->employee_id);
    }

    private function handleItemNotDelivered(DriverEvent $event): void
    {
        $stop = $this->requireTourStop($event);
        $item = $this->requireOrderItem($event);

        $qty    = (int) ($event->payload_json['qty']    ?? 0);
        $reason = (string) ($event->payload_json['reason'] ?? '');
        $note   = isset($event->payload_json['note']) ? (string) $event->payload_json['note'] : null;

        if ($qty <= 0) {
            throw new \InvalidArgumentException('item_not_delivered: payload.qty must be > 0.');
        }

        if ($reason === '') {
            throw new \InvalidArgumentException('item_not_delivered: payload.reason is required.');
        }

        $this->fulfillmentService->recordItemNotDelivered(
            $stop, $item, $qty, $reason, $note, $event->employee_id
        );
    }

    /**
     * Payment events are stored as FulfillmentEvent::TYPE_PAYMENT_RECORDED.
     * No dedicated PaymentService exists yet — the event payload holds the data.
     */
    private function handlePayment(DriverEvent $event): void
    {
        $stop = $this->requireTourStop($event);

        $amountMilli = (int) ($event->payload_json['amount_milli'] ?? 0);
        $method      = (string) ($event->payload_json['method']       ?? '');

        if ($amountMilli <= 0) {
            throw new \InvalidArgumentException('payment: payload.amount_milli must be > 0.');
        }

        if ($method === '') {
            throw new \InvalidArgumentException('payment: payload.method is required (e.g. "cash", "card").');
        }

        FulfillmentEvent::create([
            'tour_stop_id'       => $stop->id,
            'event_type'         => FulfillmentEvent::TYPE_PAYMENT_RECORDED,
            'payload_json'       => [
                'amount_milli'       => $amountMilli,
                'method'             => $method,
                'driver_employee_id' => $event->employee_id,
                'client_event_id'    => $event->client_event_id,
            ],
            'created_by_user_id' => $event->employee_id,
            'created_at'         => now(),
        ]);
    }

    private function handleEmptiesAdjustment(DriverEvent $event): void
    {
        $stop     = $this->requireTourStop($event);
        $product  = $this->requireProduct($event);

        $qtyDelta = (float) ($event->payload_json['qty_delta'] ?? 0.0);
        $note     = (string) ($event->payload_json['note']      ?? '');

        if ($qtyDelta === 0.0) {
            throw new \InvalidArgumentException('empties_adjustment: payload.qty_delta must not be zero.');
        }

        $this->fulfillmentService->recordEmptiesAdjustment(
            stop:      $stop,
            product:   $product,
            qtyDelta:  $qtyDelta,
            note:      $note,
            eventType: FulfillmentEvent::TYPE_EMPTIES_ADJUSTED,
            userId:    $event->employee_id,
        );
    }

    private function handleBreakageAdjustment(DriverEvent $event): void
    {
        $stop     = $this->requireTourStop($event);
        $product  = $this->requireProduct($event);

        $qtyDelta = (float) ($event->payload_json['qty_delta'] ?? 0.0);
        $note     = (string) ($event->payload_json['note']      ?? '');

        if ($qtyDelta === 0.0) {
            throw new \InvalidArgumentException('breakage_adjustment: payload.qty_delta must not be zero.');
        }

        $this->fulfillmentService->recordEmptiesAdjustment(
            stop:      $stop,
            product:   $product,
            qtyDelta:  $qtyDelta,
            note:      $note,
            eventType: FulfillmentEvent::TYPE_BREAKAGE_ADJUSTED,
            userId:    $event->employee_id,
        );
    }

    /**
     * Note events require payload.text (non-empty string).
     *
     * - With tour_stop_id:    stop-bound note → also stored as a FulfillmentEvent.
     * - Without tour_stop_id: general driver note → stored in driver_events only.
     */
    private function handleNote(DriverEvent $event): void
    {
        $text = (string) ($event->payload_json['text'] ?? '');

        if ($text === '') {
            throw new \InvalidArgumentException('note: payload.text is required.');
        }

        if ($event->tour_stop_id === null) {
            // General note — no domain side-effects; driver_events row is enough.
            return;
        }

        $stop = TourStop::find($event->tour_stop_id);

        if ($stop === null) {
            throw new \RuntimeException("TourStop #{$event->tour_stop_id} not found.");
        }

        FulfillmentEvent::create([
            'tour_stop_id'       => $stop->id,
            'event_type'         => FulfillmentEvent::TYPE_NOTE,
            'payload_json'       => [
                'text'               => $text,
                'driver_employee_id' => $event->employee_id,
                'client_event_id'    => $event->client_event_id,
            ],
            'created_by_user_id' => $event->employee_id,
            'created_at'         => now(),
        ]);
    }

    /**
     * Create a DriverUpload placeholder row (idempotent by device_id + client_upload_id).
     *
     * The actual file is uploaded later via POST /api/driver/upload.
     */
    private function handleUploadRequested(DriverEvent $event, array $eventData): void
    {
        $clientUploadId = (string) ($event->payload_json['client_upload_id'] ?? '');
        $uploadType     = (string) ($event->payload_json['upload_type']      ?? DriverUpload::TYPE_OTHER);

        if ($clientUploadId === '') {
            throw new \InvalidArgumentException('upload_requested: payload.client_upload_id is required.');
        }

        // Idempotent — unique constraint (device_id, client_upload_id) prevents duplicates
        $existing = DriverUpload::where('device_id', $event->device_id)
            ->where('client_upload_id', $clientUploadId)
            ->first();

        if ($existing !== null) {
            return; // already created, nothing to do
        }

        DriverUpload::create([
            'employee_id'      => $event->employee_id,
            'device_id'        => $event->device_id,
            'client_upload_id' => $clientUploadId,
            'tour_stop_id'     => $event->tour_stop_id,
            'order_id'         => $event->order_id,
            'upload_type'      => $uploadType,
            'status'           => DriverUpload::STATUS_PENDING,
        ]);
    }

    /**
     * Reference a completed DriverUpload by upload_id.
     *
     * The client calls this after a successful POST /api/driver/upload so that
     * the upload is recorded in the driver event log. If the upload is
     * stop-bound (has a tour_stop_id), backfill it from the event if missing.
     */
    private function handleUpload(DriverEvent $event): void
    {
        $uploadId = (int) ($event->payload_json['upload_id'] ?? 0);

        if ($uploadId <= 0) {
            throw new \InvalidArgumentException('upload: payload.upload_id is required.');
        }

        $upload = DriverUpload::find($uploadId);

        if ($upload === null) {
            throw new \RuntimeException("DriverUpload #{$uploadId} not found.");
        }

        // Backfill tour_stop_id on the upload row if the event carries one and
        // the upload was created without it (Mode B direct upload via PWA).
        if ($event->tour_stop_id !== null && $upload->tour_stop_id === null) {
            $upload->update(['tour_stop_id' => $event->tour_stop_id]);
        }
    }

    // =========================================================================
    // Helpers — entity resolution
    // =========================================================================

    private function requireTourStop(DriverEvent $event): TourStop
    {
        if ($event->tour_stop_id === null) {
            throw new \InvalidArgumentException(
                "Event type '{$event->event_type}' requires tour_stop_id."
            );
        }

        $stop = TourStop::find($event->tour_stop_id);

        if ($stop === null) {
            throw new \RuntimeException(
                "TourStop #{$event->tour_stop_id} not found."
            );
        }

        return $stop;
    }

    private function requireOrderItem(DriverEvent $event): OrderItem
    {
        $orderItemId = $event->order_item_id ?? (int) ($event->payload_json['order_item_id'] ?? 0);

        if ($orderItemId <= 0) {
            throw new \InvalidArgumentException(
                "Event type '{$event->event_type}' requires order_item_id."
            );
        }

        $item = OrderItem::find($orderItemId);

        if ($item === null) {
            throw new \RuntimeException("OrderItem #{$orderItemId} not found.");
        }

        return $item;
    }

    private function requireProduct(DriverEvent $event): Product
    {
        $productId = (int) ($event->payload_json['product_id'] ?? 0);

        if ($productId <= 0) {
            throw new \InvalidArgumentException(
                "Event type '{$event->event_type}' requires payload.product_id."
            );
        }

        $product = Product::find($productId);

        if ($product === null) {
            throw new \RuntimeException("Product #{$productId} not found.");
        }

        return $product;
    }

    private function eventExists(string $deviceId, string $clientEventId): bool
    {
        return DriverEvent::where('device_id', $deviceId)
            ->where('client_event_id', $clientEventId)
            ->exists();
    }
}
