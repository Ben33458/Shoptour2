<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Catalog\ProductLeergut;
use App\Models\Delivery\FulfillmentEvent;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Driver\CashRegister;
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

        $date       = $this->resolveDate($request);
        $allTours   = $this->resolveAllToursForDate($date);

        // Fallback: active in_progress tour for this driver (any date)
        if ($allTours->isEmpty() && $employeeId !== null) {
            $fallback = Tour::where('status', Tour::STATUS_IN_PROGRESS)
                ->where('driver_employee_id', $employeeId)
                ->latest('tour_date')
                ->first();
            if ($fallback) {
                $allTours = collect([$fallback]);
            }
        }

        if ($allTours->isEmpty()) {
            return response()->json([
                'tour'       => null,
                'stops'      => [],
                'date'       => $date,
                'all_tours'  => [],
            ]);
        }

        // Cash register for this employee (shared across tours)
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

        // Shared settings
        $delayThreshold = (int) DriverSetting::get('delay_threshold_percent', 30);
        $zielkassen     = $this->buildZielkassen();

        // Batch-load upload counts and metadata for all stops in one query
        $allStopIds = $allTours->flatMap(fn ($t) => $t->stops()->pluck('id'))->all();
        $uploadCounts = DriverUpload::whereIn('tour_stop_id', $allStopIds)
            ->where('status', DriverUpload::STATUS_UPLOADED)
            ->selectRaw('tour_stop_id, COUNT(*) as cnt')
            ->groupBy('tour_stop_id')
            ->pluck('cnt', 'tour_stop_id');

        $uploadsMap = DriverUpload::whereIn('tour_stop_id', $allStopIds)
            ->where('status', DriverUpload::STATUS_UPLOADED)
            ->orderBy('created_at')
            ->get(['id', 'tour_stop_id', 'mime_type', 'original_name', 'created_at'])
            ->groupBy('tour_stop_id');

        // Build full data for each tour
        $allToursData = $allTours->map(function (Tour $tour) use ($employeeId, $uploadCounts, $uploadsMap, $cashRegister, $delayThreshold, $zielkassen) {
            $isMine = $employeeId !== null && $tour->driver_employee_id === $employeeId;

            $stops = $tour->stops()
                ->with([
                    'order.items',
                    'order.deliveryAddress',
                    'order.customer.customerGroup',
                    'order.customer.defaultDeliveryAddress',
                    'itemFulfillments',
                    'events',
                ])
                ->orderBy('stop_index')
                ->get();

            $stopsData = $stops->map(function ($stop) use ($uploadCounts, $uploadsMap) {
                $deliveryAddr = $stop->order?->deliveryAddress
                    ?? $stop->order?->customer?->defaultDeliveryAddress;
                return $this->buildStopData($stop, $deliveryAddr, $uploadCounts, $uploadsMap);
            })->values();

            $customerIds  = $stops->pluck('order.customer.id')->filter()->unique()->all();
            $avgDurations = $this->buildAvgDurations($customerIds);
            $leergutMap   = $this->buildLeergutMap($stops);
            $openBalances = $this->buildOpenBalances($customerIds);

            $tourTotalVpe = $stops->sum(fn ($s) =>
                $s->order?->ninox_item_count
                ?? $s->order?->items?->sum('qty')
                ?? 0
            );

            $tourDate = $tour->tour_date instanceof \Illuminate\Support\Carbon
                ? $tour->tour_date->toDateString()
                : (string) $tour->tour_date;

            return [
                'tour' => [
                    'id'        => $tour->id,
                    'name'      => $tour->name,
                    'tour_date' => $tourDate,
                    'status'    => $tour->status,
                    'started_at' => $tour->started_at?->toIso8601String(),
                    'ended_at'   => $tour->ended_at?->toIso8601String(),
                    'total_vpe'  => (int) $tourTotalVpe,
                    'is_mine'    => $isMine,
                ],
                'stops'         => $stopsData,
                'cash_register' => $isMine ? $cashRegister : null,
                'avg_durations' => $avgDurations,
                'leergut_map'   => $leergutMap,
                'delay_threshold' => $delayThreshold,
                'open_balances' => $openBalances,
                'zielkassen'    => $isMine ? $zielkassen : [],
            ];
        })->values();

        // Primary tour: first "mine" or first overall
        $primary = $allToursData->firstWhere('tour.is_mine', true) ?? $allToursData->first();

        return response()->json([
            // Backward-compat keys (primary tour)
            'tour'            => $primary['tour'],
            'stops'           => $primary['stops'],
            'cash_register'   => $primary['cash_register'],
            'avg_durations'   => $primary['avg_durations'],
            'leergut_map'     => $primary['leergut_map'],
            'delay_threshold' => $primary['delay_threshold'],
            'open_balances'   => $primary['open_balances'],
            'zielkassen'      => $primary['zielkassen'],
            // Multi-tour support
            'all_tours'       => $allToursData,
            'date'            => $date,
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
     * All planned/in-progress tours for the given date (regardless of driver assignment).
     *
     * @return \Illuminate\Support\Collection<int, Tour>
     */
    private function resolveAllToursForDate(string $date): \Illuminate\Support\Collection
    {
        return Tour::whereDate('tour_date', $date)
            ->whereIn('status', [Tour::STATUS_PLANNED, Tour::STATUS_IN_PROGRESS])
            ->orderBy('id')
            ->get();
    }

    /**
     * Build open invoice balances per customer_id (milli-cents).
     * Only considers finalized invoices with remaining balance > 0.
     *
     * @param  int[] $customerIds
     * @return array<int, int>  customer_id → open balance milli
     */
    private function buildOpenBalances(array $customerIds): array
    {
        if (empty($customerIds)) {
            return [];
        }

        $rows = DB::table('invoices as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->leftJoin(
                DB::raw('(SELECT invoice_id, SUM(amount_milli) as paid FROM payments GROUP BY invoice_id) as p'),
                'p.invoice_id', '=', 'i.id'
            )
            ->whereIn('o.customer_id', $customerIds)
            ->where('i.status', 'finalized')
            ->whereRaw('(i.total_gross_milli - COALESCE(p.paid, 0)) > 0')
            ->select(
                'o.customer_id',
                DB::raw('SUM(i.total_gross_milli - COALESCE(p.paid, 0)) as open_milli')
            )
            ->groupBy('o.customer_id')
            ->get();

        return $rows->pluck('open_milli', 'customer_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function buildStopData(TourStop $stop, ?Address $deliveryAddr, $uploadCounts, $uploadsMap = []): array
    {
        return [
            'id'          => $stop->id,
            'tour_id'     => $stop->tour_id,
            'order_id'    => $stop->order_id,
            'stop_index'  => $stop->stop_index,
            'status'      => $stop->status,
            'arrived_at'  => $stop->arrived_at?->toIso8601String(),
            'finished_at' => $stop->finished_at?->toIso8601String(),
            'departed_at' => $stop->departed_at?->toIso8601String(),

            'customer_id'      => $stop->order?->customer?->id,
            'customer_name'    => $stop->order?->customer
                ? trim(($stop->order->customer->first_name ?? '') . ' ' . ($stop->order->customer->last_name ?? ''))
                : null,
            'delivery_address' => $deliveryAddr?->oneLiner()
                ?? $stop->order?->customer?->delivery_address_text,
            'delivery_note'    => $stop->order?->customer?->delivery_note,
            'is_deposit_exempt' => (bool) ($stop->order?->customer?->customerGroup?->is_deposit_exempt ?? false),

            'drop_off_location'        => $deliveryAddr?->drop_off_location,
            'drop_off_location_custom' => $deliveryAddr?->drop_off_location_custom,
            'leave_at_door'            => (bool) ($deliveryAddr?->leave_at_door ?? false),

            'payments_recorded' => $stop->events
                ->where('event_type', FulfillmentEvent::TYPE_PAYMENT_RECORDED)
                ->map(fn ($e) => [
                    'amount_milli' => (int) ($e->payload_json['amount_milli'] ?? 0),
                    'method'       => (string) ($e->payload_json['method'] ?? ''),
                ])->values(),

            'order' => $stop->order ? [
                'id'                       => $stop->order->id,
                'total_gross_milli'        => $stop->order->total_gross_milli,
                'total_pfand_brutto_milli' => $stop->order->total_pfand_brutto_milli,
                'notes'                    => $stop->order->notes,
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

            'uploads_count' => (int) ($uploadCounts[$stop->id] ?? 0),
            'uploads'       => ($uploadsMap[$stop->id] ?? collect())->map(fn ($u) => [
                'id'            => $u->id,
                'mime_type'     => $u->mime_type,
                'original_name' => $u->original_name,
                'created_at'    => $u->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /**
     * Build list of target cash registers for tour-end deposit.
     * Excludes driver wallets (type = wallet).
     *
     * @return array<int, array{id: int, name: string, register_type: string}>
     */
    private function buildZielkassen(): array
    {
        return CashRegister::where('is_active', true)
            ->whereIn('register_type', [
                CashRegister::TYPE_SAFE,
                CashRegister::TYPE_REGISTER,
                CashRegister::TYPE_BANK,
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => [
                'id'            => $r->id,
                'name'          => $r->name,
                'register_type' => $r->register_type,
            ])
            ->values()
            ->all();
    }
}
