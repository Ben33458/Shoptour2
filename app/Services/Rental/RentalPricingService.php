<?php
declare(strict_types=1);
namespace App\Services\Rental;

use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPriceRule;
use App\Models\Rental\RentalTimeModel;
use Illuminate\Support\Collection;

/**
 * Preisfindung für Mietartikel.
 * Abrechnung NUR pro Mietzeitraum (nicht pro Tag).
 *
 * Returned price in milli-cents.
 */
class RentalPricingService
{
    /**
     * Ermittelt den Netto-Stückpreis für eine Buchung in Milli-Cent.
     * Gibt null zurück wenn keine Preisregel gefunden.
     */
    public function resolveUnitPriceMilli(
        RentalItem $item,
        RentalTimeModel $timeModel,
        int $quantity,
        ?int $packagingUnitId = null,
        ?int $customerGroupId = null,
    ): ?int {
        $query = RentalPriceRule::query()
            ->where('rental_item_id', $item->id)
            ->where('rental_time_model_id', $timeModel->id)
            ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->toDateString()))
            ->where(fn($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->toDateString()))
            ->where(fn($q) => $q->whereNull('min_quantity')->orWhere('min_quantity', '<=', $quantity))
            ->where(fn($q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity));

        if ($packagingUnitId) {
            $query->where(fn($q) => $q->where('packaging_unit_id', $packagingUnitId)->orWhereNull('packaging_unit_id'));
        }

        if ($customerGroupId) {
            $query->where(fn($q) => $q->where('customer_group_id', $customerGroupId)->orWhereNull('customer_group_id'));
        }

        // Most specific rule first: with packaging_unit, then customer_group, then quantity range
        $rule = $query
            ->orderByRaw('packaging_unit_id IS NULL ASC')
            ->orderByRaw('customer_group_id IS NULL ASC')
            ->orderByDesc('min_quantity')
            ->first();

        return $rule?->price_net_milli;
    }

    /**
     * Berechnet Gesamtpreis für eine Buchungsposition in Milli-Cent.
     */
    public function calculateTotal(
        RentalItem $item,
        RentalTimeModel $timeModel,
        int $quantity,
        ?int $packagingUnitId = null,
        ?int $customerGroupId = null,
    ): array {
        $unitPrice = $this->resolveUnitPriceMilli($item, $timeModel, $quantity, $packagingUnitId, $customerGroupId);

        if ($unitPrice === null) {
            return ['unit_price_net_milli' => 0, 'total_price_net_milli' => 0, 'found' => false];
        }

        return [
            'unit_price_net_milli'  => $unitPrice,
            'total_price_net_milli' => $unitPrice * $quantity,
            'found'                 => true,
        ];
    }
}
