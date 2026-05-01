<?php

declare(strict_types=1);

namespace App\Services\Rental;

use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPackagingUnit;
use App\Models\Rental\RentalTimeModel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Session-basierter Warenkorb für Mietartikel.
 *
 * Struktur in der Session (Schlüssel: rental_cart):
 * [
 *   'date_from'     => '2026-05-01',
 *   'date_until'    => '2026-05-04',
 *   'time_model_id' => 3,
 *   'items' => [
 *       '<rental_item_id>' => [
 *           'qty'               => 2,
 *           'packaging_unit_id' => 7|null,
 *           'pieces'            => 48,   // qty * pieces_per_pack (packaging_based)
 *       ],
 *   ]
 * ]
 */
class RentalCartService
{
    private const SESSION_KEY = 'rental_cart';

    public function __construct(
        private readonly Request $request,
        private readonly RentalAvailabilityService $availability,
        private readonly RentalPricingService $pricing,
    ) {}

    // ── Dates & Time Model ────────────────────────────────────────────────────

    public function setDates(string $from, string $until): void
    {
        $cart = $this->get();
        $cart['date_from']  = $from;
        $cart['date_until'] = $until;
        $this->save($cart);
    }

    public function setTimeModel(int $timeModelId): void
    {
        $cart = $this->get();
        $cart['time_model_id'] = $timeModelId;
        $this->save($cart);
    }

    public function getDateFrom(): ?Carbon
    {
        $raw = $this->get()['date_from'] ?? null;
        return $raw ? Carbon::parse($raw) : null;
    }

    public function getDateUntil(): ?Carbon
    {
        $raw = $this->get()['date_until'] ?? null;
        return $raw ? Carbon::parse($raw) : null;
    }

    public function getTimeModel(): ?RentalTimeModel
    {
        $id = $this->get()['time_model_id'] ?? null;
        if ($id) {
            $model = RentalTimeModel::find($id);
            // If stored model is inactive or has no price rules, fall back to default
            if ($model && $model->active) {
                return $model;
            }
        }
        return RentalTimeModel::where('default_for_events', true)->where('active', true)->first()
            ?? RentalTimeModel::where('active', true)->orderBy('sort_order')->first();
    }

    // ── Items ─────────────────────────────────────────────────────────────────

    public function addItem(int $itemId, int $qty, ?int $packagingUnitId = null): void
    {
        $cart = $this->get();

        $pieces = $qty;
        if ($packagingUnitId) {
            $pu = RentalPackagingUnit::find($packagingUnitId);
            if ($pu) {
                $pieces = $qty * $pu->pieces_per_pack;
            }
        }

        $cart['items'][(string) $itemId] = [
            'qty'               => $qty,
            'packaging_unit_id' => $packagingUnitId,
            'pieces'            => $pieces,
        ];

        $this->save($cart);
    }

    public function removeItem(int $itemId): void
    {
        $cart = $this->get();
        unset($cart['items'][(string) $itemId]);
        $this->save($cart);
    }

    public function updateItemQty(int $itemId, int $qty): void
    {
        $cart = $this->get();
        $key  = (string) $itemId;

        if (! isset($cart['items'][$key])) {
            return;
        }

        if ($qty <= 0) {
            $this->removeItem($itemId);
            return;
        }

        $packagingUnitId = $cart['items'][$key]['packaging_unit_id'] ?? null;
        $pieces = $qty;
        if ($packagingUnitId) {
            $pu = RentalPackagingUnit::find($packagingUnitId);
            if ($pu) {
                $pieces = $qty * $pu->pieces_per_pack;
            }
        }

        $cart['items'][$key]['qty']    = $qty;
        $cart['items'][$key]['pieces'] = $pieces;
        $this->save($cart);
    }

    public function clear(): void
    {
        $this->request->session()->forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return empty($this->get()['items']);
    }

    public function count(): int
    {
        return count($this->get()['items'] ?? []);
    }

    // ── Enriched Summary ──────────────────────────────────────────────────────

    /**
     * Returns a Collection of enriched line items for display / checkout.
     * Each item: model, qty, pieces, packaging_unit, unit_price_milli, total_price_milli, available, time_model
     */
    public function getItemsSummary(?int $customerGroupId = null): Collection
    {
        $cart      = $this->get();
        $items     = $cart['items'] ?? [];
        $timeModel = $this->getTimeModel();
        $from      = $this->getDateFrom();
        $until     = $this->getDateUntil();

        $result = collect();

        foreach ($items as $itemId => $row) {
            $rentalItem = RentalItem::with('category')->find((int) $itemId);
            if (! $rentalItem) {
                continue;
            }

            $packagingUnit = $row['packaging_unit_id']
                ? RentalPackagingUnit::find($row['packaging_unit_id'])
                : null;

            $pricing = ['unit_price_net_milli' => null, 'total_price_net_milli' => null, 'found' => false];
            if ($timeModel) {
                $pricing = $this->pricing->calculateTotal(
                    $rentalItem,
                    $timeModel,
                    $row['qty'],
                    $row['packaging_unit_id'],
                    $customerGroupId,
                );
            }

            $available = null;
            if ($from && $until) {
                $available = $this->availability->getAvailable($rentalItem, $from, $until, $row['qty']);
            }

            $result->push([
                'item'                  => $rentalItem,
                'qty'                   => $row['qty'],
                'pieces'                => $row['pieces'],
                'packaging_unit'        => $packagingUnit,
                'unit_price_net_milli'  => $pricing['unit_price_net_milli'],
                'total_price_net_milli' => $pricing['total_price_net_milli'],
                'price_found'           => $pricing['found'],
                'available_qty'         => $available,
                'time_model'            => $timeModel,
            ]);
        }

        return $result;
    }

    /**
     * Total net price across all cart items in milli-cent.
     */
    public function totalNetMilli(?int $customerGroupId = null): int
    {
        return $this->getItemsSummary($customerGroupId)
            ->sum(fn($row) => $row['total_price_net_milli'] ?? 0);
    }

    // ── Raw Session ───────────────────────────────────────────────────────────

    public function get(): array
    {
        return $this->request->session()->get(self::SESSION_KEY, [
            'items' => [],
        ]);
    }

    private function save(array $cart): void
    {
        $this->request->session()->put(self::SESSION_KEY, $cart);
    }
}
