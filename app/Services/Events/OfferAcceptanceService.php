<?php

declare(strict_types=1);

namespace App\Services\Events;

use App\Models\Events\EventOfferVersion;
use App\Models\Events\EventRentalReservation;
use App\Models\Events\EventTask;
use App\Models\Orders\Order;
use App\Services\Orders\OrderNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Processes the acceptance of an event offer version.
 *
 * On success: creates an Order, marks the offer as converted, confirms the occurrence.
 * On failure: marks the offer needs_review, creates a manual-check task, logs errors.
 */
class OfferAcceptanceService
{
    public function __construct(
        private readonly OrderNumberService    $orderNumberService,
        private readonly EventOfferAuditService $auditService,
    ) {}

    /**
     * Accept an offer version.
     *
     * @return array{success: bool, order: Order|null, errors: array, review_needed: bool}
     */
    public function accept(EventOfferVersion $version, int $userId): array
    {
        $errors = $this->validate($version);

        if (!empty($errors)) {
            return $this->handleFailure($version, $userId, $errors);
        }

        return DB::transaction(function () use ($version, $userId) {
            try {
                $order = $this->createOrderFromOffer($version, $userId);

                // Update offer version
                $version->update([
                    'status'             => 'converted_to_order',
                    'accepted_at'        => now(),
                    'converted_order_id' => $order->id,
                    'conversion_status'  => 'ok',
                    'conversion_error'   => null,
                ]);

                // Update occurrence
                $occurrence = $version->occurrence;
                $occurrence->update([
                    'event_status' => 'confirmed',
                    'offer_status' => 'converted_to_order',
                ]);

                // Harden all soft rental reservations to hard
                EventRentalReservation::where('event_occurrence_id', $occurrence->id)
                    ->where('reservation_type', 'soft')
                    ->whereNotIn('reservation_status', ['cancelled', 'conflict'])
                    ->update([
                        'reservation_type'   => 'hard',
                        'reservation_status' => 'confirmed',
                    ]);

                $this->auditService->log('event.offer.accepted', $version, [
                    'offer_number' => $version->offer_number,
                    'order_id'     => $order->id,
                    'order_number' => $order->order_number,
                ], $userId);

                return [
                    'success'       => true,
                    'order'         => $order,
                    'errors'        => [],
                    'review_needed' => false,
                ];
            } catch (\Throwable $e) {
                // If something unexpected fails inside the transaction, re-throw
                // so the transaction rolls back; the outer catch handles audit logging
                throw $e;
            }
        });
    }

    // ── Private helpers ────────────────────────────────────────

    /**
     * Validate the offer version before acceptance.
     *
     * @return array  List of human-readable error strings (empty = valid)
     */
    private function validate(EventOfferVersion $version): array
    {
        $errors = [];

        $version->load(['occurrence.logisticsAppointments', 'items']);
        $occurrence = $version->occurrence;

        if ($occurrence === null || $occurrence->customer_id === null) {
            $errors[] = 'Kein Kunde am Vorgang hinterlegt.';
        }

        if ($occurrence?->event_start_at === null) {
            $errors[] = 'Kein Veranstaltungsdatum hinterlegt.';
        }

        if ($version->items->isEmpty()) {
            $errors[] = 'Das Angebot enthält keine Positionen.';
        }

        $allFreeText = $version->items->every(
            fn ($i) => $i->is_free_text && $i->article_id === null
        );
        if (!$version->items->isEmpty() && $allFreeText) {
            $errors[] = 'Alle Positionen sind Freitext ohne Artikelverknüpfung.';
        }

        if ($version->net_total_milli === null) {
            $errors[] = 'Kein Nettobetrag hinterlegt.';
        }

        $hasDelivery = ($occurrence?->logisticsAppointments ?? collect())->isNotEmpty();
        if (!$hasDelivery) {
            $errors[] = 'Kein Liefertermin hinterlegt.';
        }

        // Check for rental conflicts
        $hasConflict = EventRentalReservation::where('event_occurrence_id', $occurrence?->id)
            ->where('reservation_status', 'conflict')
            ->exists();
        if ($hasConflict) {
            $errors[] = 'Festbedarf-Konflikt bei Reservierungen (reservation_status=conflict).';
        }

        return $errors;
    }

    /**
     * Create an Order from the accepted offer version.
     */
    private function createOrderFromOffer(EventOfferVersion $version, int $userId): Order
    {
        $occurrence = $version->occurrence;
        $customer   = $occurrence->customer;

        // Find first delivery appointment date
        $deliveryDate = $occurrence->logisticsAppointments()
            ->where('type', 'delivery')
            ->orderBy('scheduled_date')
            ->value('scheduled_date');

        $deliveryDate = $deliveryDate ?? $occurrence->event_start_at?->toDateString();

        $orderNumber = DB::transaction(
            fn () => $this->orderNumberService->generate()
        );

        $order = Order::create([
            'customer_id'                  => $occurrence->customer_id,
            'customer_group_id_snapshot'   => $customer?->customer_group_id ?? 1,
            'status'                       => 'confirmed',
            'order_number'                 => $orderNumber,
            'delivery_type'                => 'home_delivery',
            'delivery_date'                => $deliveryDate,
            'total_net_milli'              => $version->net_total_milli ?? 0,
            'total_gross_milli'            => $version->gross_total_milli ?? 0,
            'total_pfand_brutto_milli'     => $version->deposit_total_milli ?? 0,
            'is_event_order'               => true,
            'notes'                        => 'Automatisch aus Angebot ' . ($version->offer_number ?? $version->external_offer_number) . ' erzeugt',
        ]);

        return $order;
    }

    /**
     * Handle a validation failure: mark the offer needs_review, create a task, audit log.
     *
     * @return array{success: bool, order: null, errors: array, review_needed: bool}
     */
    private function handleFailure(EventOfferVersion $version, int $userId, array $errors): array
    {
        $version->update([
            'conversion_status' => 'needs_review',
            'conversion_error'  => $errors,
        ]);

        // Create manual-check task
        EventTask::create([
            'event_occurrence_id' => $version->event_occurrence_id,
            'title'               => 'Bestellungserzeugung manuell prüfen',
            'description'         => implode("\n", $errors),
            'status'              => 'open',
            'task_type'           => 'offer',
            'created_by_user_id'  => $userId,
        ]);

        $this->auditService->log('event.offer.acceptance_failed', $version, [
            'offer_number' => $version->offer_number,
            'errors'       => $errors,
        ], $userId);

        return [
            'success'       => false,
            'order'         => null,
            'errors'        => $errors,
            'review_needed' => true,
        ];
    }
}
