<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ProductLeergut;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Driver\DriverSetting;
use App\Models\Driver\DriverUpload;
use App\Models\Employee\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET /api/driver/bootstrap[?date=YYYY-MM-DD]
 *
 * Returns the full dataset the driver PWA needs to work offline:
 *   - the tour for the requested date (defaults to today)
 *   - all stops for that tour, with embedded order/customer/items
 *
 * Date selection:
 *   Pass ?date=YYYY-MM-DD to load a tour for any date (useful for the
 *   date-picker in the empty-state card of the PWA).  Omitting the parameter
 *   behaves exactly as before (today's date).
 *
 * If no tour is found for the given date and the authenticated employee,
 * the response still returns 200 with tour=null so the PWA can render a
 * "no tour today" state without treating it as an error.
 *
 * Richer stop payload (v2 additions):
 *   - customer_name, delivery_address, delivery_note  — driver-display info
 *   - total_gross_milli, total_pfand_brutto_milli      — order totals for receipt
 *   - order.items now includes product_name_snapshot + artikelnummer_snapshot
 */
class DriverBootstrapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var int|null $employeeId */
        $employeeId = $request->attributes->get('driver_employee_id');

        $date = $this->resolveDate($request);
        $tour = $this->resolveTour($employeeId, $date);

        if ($tour === null) {
            return response()->json([
                'tour'  => null,
                'stops' => [],
                'date'  => $date,
            ]);
        }

        $stops = $tour->stops()
            ->with([
                'order.items',
                'order.customer',
                'itemFulfillments',
            ])
            ->orderBy('stop_index')
            ->get();

        // Load upload counts per stop in one query (avoid N+1)
        $stopIds      = $stops->pluck('id')->all();
        $uploadCounts = DriverUpload::whereIn('tour_stop_id', $stopIds)
            ->where('status', DriverUpload::STATUS_UPLOADED)
            ->selectRaw('tour_stop_id, COUNT(*) as cnt')
            ->groupBy('tour_stop_id')
            ->pluck('cnt', 'tour_stop_id');

        $stopsData = $stops->map(fn ($stop) => [
            'id'          => $stop->id,
            'tour_id'     => $stop->tour_id,
            'order_id'    => $stop->order_id,
            'stop_index'  => $stop->stop_index,
            'status'      => $stop->status,
            'arrived_at'  => $stop->arrived_at?->toIso8601String(),
            'finished_at' => $stop->finished_at?->toIso8601String(),
            'departed_at' => $stop->departed_at?->toIso8601String(),

            // Customer / delivery details for driver display
            'customer_name'    => $stop->order?->customer
                ? trim(($stop->order->customer->first_name ?? '') . ' ' . ($stop->order->customer->last_name ?? ''))
                : null,
            'delivery_address' => $stop->order?->deliveryAddress?->oneLiner()
                ?? $stop->order?->customer?->defaultDeliveryAddress?->oneLiner()
                ?? $stop->order?->customer?->delivery_address_text,
            'delivery_note'    => $stop->order?->customer?->delivery_note,

            'order' => $stop->order ? [
                'id'                       => $stop->order->id,
                'total_gross_milli'        => $stop->order->total_gross_milli,
                'total_pfand_brutto_milli' => $stop->order->total_pfand_brutto_milli,
                'items'                    => $stop->order->items->map(fn ($item) => [
                    'id'                     => $item->id,
                    'product_id'             => $item->product_id,
                    'quantity'               => $item->qty,
                    'product_name'           => $item->product_name_snapshot,
                    'artikelnummer'          => $item->artikelnummer_snapshot,
                    'unit_price_gross_milli' => $item->unit_price_gross_milli,
                    'unit_deposit_milli'     => $item->unit_deposit_milli,
                ])->values(),
            ] : null,

            'item_fulfillments' => $stop->itemFulfillments->map(fn ($f) => [
                'order_item_id'        => $f->order_item_id,
                'delivered_qty'        => $f->delivered_qty,
                'not_delivered_qty'    => $f->not_delivered_qty,
                'not_delivered_reason' => $f->not_delivered_reason,
                'note'                 => $f->note,
            ])->values(),

            // Count of successfully uploaded files for this stop
            'uploads_count' => (int) ($uploadCounts[$stop->id] ?? 0),
        ])->values();

        // Cash register for this employee
        $cashRegister = null;
        if ($employeeId !== null) {
            $employee = Employee::find($employeeId);
            if ($employee && $employee->cash_register_id) {
                $cashRegister = [
                    'id'   => $employee->cash_register_id,
                    'name' => $employee->cashRegister?->name,
                ];
            }
        }

        // Average stop duration per customer (seconds) across last 60 days
        $avgDurations = $this->buildAvgDurations($stops->pluck('order.customer.id')->filter()->unique()->all());

        // Leergut map: product_id → leergut article info (from wawi_artikel_attribute)
        $leergutMap = $this->buildLeergutMap($stops);

        // Delay threshold from settings (default 30%)
        $delayThreshold = (int) DriverSetting::get('delay_threshold_percent', 30);

        return response()->json([
            'tour' => [
                'id'         => $tour->id,
                'tour_date'  => $tour->tour_date instanceof \Illuminate\Support\Carbon
                    ? $tour->tour_date->toDateString()
                    : (string) $tour->tour_date,
                'status'     => $tour->status,
                'started_at' => $tour->started_at?->toIso8601String(),
                'ended_at'   => $tour->ended_at?->toIso8601String(),
            ],
            'stops'           => $stopsData,
            'date'            => $date,
            'cash_register'   => $cashRegister,
            'avg_durations'   => $avgDurations,
            'leergut_map'     => $leergutMap,
            'delay_threshold' => $delayThreshold,
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Resolve the requested date from the ?date= query param, or fall back to today.
     */
    private function resolveDate(Request $request): string
    {
        $raw = $request->query('date');

        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        return now()->toDateString();
    }

    /**
     * Build avg stop duration in seconds per customer_id over the last 60 days.
     * Uses arrived_at → departed_at (preferred) or arrived_at → finished_at.
     *
     * @param  int[] $customerIds
     * @return array<int, int>  customer_id → avg seconds
     */
    private function buildAvgDurations(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $since = now()->subDays(60)->toDateString();

        $rows = DB::table('tour_stops as ts')
            ->join('tours as t', 't.id', '=', 'ts.tour_id')
            ->join('orders as o', 'o.id', '=', 'ts.order_id')
            ->whereIn('o.customer_id', $customerIds)
            ->where('t.tour_date', '>=', $since)
            ->whereNotNull('ts.arrived_at')
            ->where(function ($q): void {
                $q->whereNotNull('ts.departed_at')->orWhereNotNull('ts.finished_at');
            })
            ->select(
                'o.customer_id',
                DB::raw('AVG(TIMESTAMPDIFF(SECOND, ts.arrived_at, COALESCE(ts.departed_at, ts.finished_at))) as avg_seconds')
            )
            ->groupBy('o.customer_id')
            ->get();

        return $rows->pluck('avg_seconds', 'customer_id')
            ->map(fn ($v) => (int) round((float) $v))
            ->all();
    }

    /**
     * Build leergut map: product_id → { leergut_kArtikel, leergut_name, leergut_art_nr,
     *                                    unit_price_net_milli, unit_price_gross_milli }
     * Uses wawi_artikel_attribute (PfandARtNr) to find the leergut article.
     */
    private function buildLeergutMap(\Illuminate\Support\Collection $stops): array
    {
        $productIds = $stops->flatMap(fn ($s) => $s->order?->items?->pluck('product_id') ?? collect())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($productIds)) {
            return [];
        }

        $leergutRows = ProductLeergut::whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        $map = [];
        foreach ($leergutRows as $productId => $row) {
            $map[(int) $productId] = [
                'leergut_art_nr'          => $row->leergut_art_nr,
                'leergut_name'            => $row->leergut_name,
                'unit_price_net_milli'    => $row->unit_price_net_milli,
                'unit_price_gross_milli'  => $row->unit_price_gross_milli,
            ];
        }

        return $map;
    }

    /**
     * Find a planned/in-progress tour for the given date and optional employee.
     */
    private function resolveTour(?int $employeeId, string $date): ?Tour
    {
        $query = Tour::whereDate('tour_date', $date)
            ->whereIn('status', [Tour::STATUS_PLANNED, Tour::STATUS_IN_PROGRESS]);

        if ($employeeId !== null) {
            $query->where('driver_employee_id', $employeeId);
        }

        return $query->first();
    }
}
