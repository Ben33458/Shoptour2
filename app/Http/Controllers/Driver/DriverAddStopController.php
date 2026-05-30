<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Pricing\Customer;
use App\Services\Driver\AdhocStopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/driver/add-stop
 *
 * Adds an ad-hoc TourStop to the driver's active tour for today.
 * Reuses an existing open order for the customer on the same day if one exists,
 * to avoid duplicate orders.
 *
 * Body: { customer_id: int, note?: string }
 */
class DriverAddStopController extends Controller
{
    public function __construct(private readonly AdhocStopService $adhocStopService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $employeeId = $request->attributes->get('driver_employee_id');

        // Find the driver's active tour for today
        $tour = Tour::whereDate('tour_date', now()->toDateString())
            ->whereIn('status', [Tour::STATUS_PLANNED, Tour::STATUS_IN_PROGRESS])
            ->when($employeeId !== null, fn ($q) => $q->where('driver_employee_id', $employeeId))
            ->first();

        if (! $tour) {
            return response()->json(
                ['error' => 'Keine aktive Tour für heute gefunden.'],
                422
            );
        }

        $customer = Customer::where('active', true)->find($validated['customer_id']);
        if (! $customer) {
            return response()->json(
                ['error' => 'Kunde nicht gefunden oder inaktiv.'],
                422
            );
        }

        $result = $this->adhocStopService->createStop($tour, $customer, $validated['note'] ?? null);

        /** @var TourStop $stop */
        $stop = $result['stop'];

        return response()->json([
            'stop'                  => $this->formatStop($stop),
            'reused_existing_order' => $result['reused_existing_order'],
            'order_id'              => $result['order_id'],
        ], 201);
    }

    /** Format a TourStop in the same shape as the bootstrap /stops array. */
    private function formatStop(TourStop $stop): array
    {
        return [
            'id'                => $stop->id,
            'tour_id'           => $stop->tour_id,
            'order_id'          => $stop->order_id,
            'stop_index'        => $stop->stop_index,
            'status'            => $stop->status,
            'arrived_at'        => null,
            'finished_at'       => null,
            'departed_at'       => null,

            'customer_id'       => $stop->order?->customer?->id,
            'customer_name'     => $stop->order?->customer
                ? trim(($stop->order->customer->first_name ?? '') . ' ' . ($stop->order->customer->last_name ?? ''))
                : null,
            'delivery_address'  => $stop->order?->customer?->defaultDeliveryAddress?->oneLiner()
                ?? $stop->order?->customer?->delivery_address_text,
            'delivery_note'     => $stop->order?->customer?->delivery_note,
            'is_deposit_exempt' => (bool) ($stop->order?->customer?->customerGroup?->is_deposit_exempt ?? false),

            'order' => $stop->order ? [
                'id'                       => $stop->order->id,
                'total_gross_milli'        => $stop->order->total_gross_milli,
                'total_pfand_brutto_milli' => $stop->order->total_pfand_brutto_milli,
                'items'                    => [],
            ] : null,

            'item_fulfillments'  => [],
            'payments_recorded'  => [],
            'uploads_count'      => 0,
        ];
    }
}
