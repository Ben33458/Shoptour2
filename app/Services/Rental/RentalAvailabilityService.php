<?php
declare(strict_types=1);
namespace App\Services\Rental;

use App\Models\Rental\RentalBookingAllocation;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalInventoryUnit;
use App\Models\Rental\RentalItem;
use App\Models\Rental\RentalPackagingUnit;
use Carbon\Carbon;

/**
 * Verfügbarkeitslogik für alle 4 Inventarmodi.
 *
 * Reservierungen blockieren SOFORT.
 * Standard: keine Überbuchung (allow_overbooking = false).
 */
class RentalAvailabilityService
{
    /**
     * Gibt verfügbare Menge zurück. 0 = nicht verfügbar.
     */
    public function getAvailable(RentalItem $item, Carbon $from, Carbon $until, int $requestedQty = 1): int
    {
        return match ($item->inventory_mode) {
            RentalItem::MODE_UNIT       => $this->availableUnits($item, $from, $until),
            RentalItem::MODE_QUANTITY   => $this->availableQuantity($item, $from, $until),
            RentalItem::MODE_PACKAGING  => $this->availablePackaging($item, $from, $until),
            RentalItem::MODE_COMPONENT  => $this->availableComponents($item, $from, $until, $requestedQty),
            default => 0,
        };
    }

    /**
     * Prüft ob eine Buchung möglich ist.
     * Berücksichtigt allow_overbooking.
     */
    public function canBook(RentalItem $item, Carbon $from, Carbon $until, int $qty = 1): bool
    {
        if ($item->allow_overbooking) {
            return true;
        }
        return $this->getAvailable($item, $from, $until, $qty) >= $qty;
    }

    /**
     * unit_based: Freie konkrete Inventareinheiten im Zeitraum.
     */
    public function availableUnits(RentalItem $item, Carbon $from, Carbon $until): int
    {
        $totalUnits = $item->inventoryUnits()
            ->whereIn('status', [RentalInventoryUnit::STATUS_AVAILABLE, RentalInventoryUnit::STATUS_RESERVED])
            ->count();

        $unitIds = $item->inventoryUnits()->pluck('id');

        $reserved = RentalBookingAllocation::query()
            ->whereIn('rental_inventory_unit_id', $unitIds)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->where(function ($q) use ($from, $until) {
                $q->where('allocated_from', '<', $until)
                  ->where('allocated_until', '>', $from);
            })
            ->count();

        return max(0, $totalUnits - $reserved);
    }

    /**
     * unit_based: Freie spezifische Einheit prüfen.
     * Wunsch-Gerät ist verbindlich buchbar, wenn verfügbar.
     */
    public function isUnitAvailable(RentalInventoryUnit $unit, Carbon $from, Carbon $until): bool
    {
        if (!in_array($unit->status, [RentalInventoryUnit::STATUS_AVAILABLE, RentalInventoryUnit::STATUS_RESERVED])) {
            return false;
        }

        return !RentalBookingAllocation::query()
            ->where('rental_inventory_unit_id', $unit->id)
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->where('allocated_from', '<', $until)
            ->where('allocated_until', '>', $from)
            ->exists();
    }

    /**
     * quantity_based: Verfügbar = Gesamtbestand - Reservierungen - Defekte.
     */
    public function availableQuantity(RentalItem $item, Carbon $from, Carbon $until): int
    {
        $total = $item->total_quantity ?? 0;

        $defectiveUnits = $item->inventoryUnits()
            ->whereIn('status', [RentalInventoryUnit::STATUS_DEFECTIVE, RentalInventoryUnit::STATUS_RETIRED, RentalInventoryUnit::STATUS_MAINTENANCE])
            ->count();

        $reserved = RentalBookingItem::query()
            ->where('rental_item_id', $item->id)
            ->whereIn('status', ['unreviewed', 'reserved', 'confirmed', 'delivered'])
            ->whereHas('order', function ($q) use ($from, $until) {
                $q->where('is_event_order', true)
                  ->where(function ($inner) use ($from, $until) {
                      $inner->where('desired_delivery_date', '<', $until->toDateString())
                            ->where('desired_pickup_date', '>', $from->toDateString());
                  });
            })
            ->sum('quantity');

        return max(0, $total - $defectiveUnits - (int) $reserved);
    }

    /**
     * packaging_based: Verfügbare Packs (Gläser nur in VPE buchbar).
     * Bruch reduziert dauerhaft den Bestand.
     */
    public function availablePackaging(RentalItem $item, Carbon $from, Carbon $until): int
    {
        // Sum of all packaging unit packs available
        $totalPacks = $item->packagingUnits()->where('active', true)->sum('available_packs');

        $reservedPacks = RentalBookingItem::query()
            ->where('rental_item_id', $item->id)
            ->whereNotNull('packaging_unit_id')
            ->whereIn('status', ['unreviewed', 'reserved', 'confirmed', 'delivered'])
            ->whereHas('order', function ($q) use ($from, $until) {
                $q->where('desired_delivery_date', '<', $until->toDateString())
                  ->where('desired_pickup_date', '>', $from->toDateString());
            })
            ->sum('quantity'); // quantity = number of packs

        return max(0, (int) $totalPacks - (int) $reservedPacks);
    }

    /**
     * component_based: Setverfügbarkeit aus freien Komponenten.
     * 1 Garnitur = 1 Tisch + 2 Bänke => min(tische/1, bänke/2)
     */
    public function availableComponents(RentalItem $item, Carbon $from, Carbon $until, int $requestedSets = 1): int
    {
        $components = $item->components()->with('component')->get();

        if ($components->isEmpty()) {
            return 0;
        }

        $maxSets = PHP_INT_MAX;

        foreach ($components as $component) {
            $componentItem = $component->component;
            $availableOfComponent = $this->getAvailable($componentItem, $from, $until);
            $setsFromComponent = intdiv($availableOfComponent, $component->quantity);
            $maxSets = min($maxSets, $setsFromComponent);
        }

        return $maxSets === PHP_INT_MAX ? 0 : $maxSets;
    }

    /**
     * Gibt alle verfügbaren unit_based Einheiten zurück.
     */
    public function getAvailableUnits(RentalItem $item, Carbon $from, Carbon $until): \Illuminate\Database\Eloquent\Collection
    {
        $reservedUnitIds = RentalBookingAllocation::query()
            ->whereHas('inventoryUnit', fn($q) => $q->where('rental_item_id', $item->id))
            ->whereNotIn('status', ['cancelled', 'returned'])
            ->where('allocated_from', '<', $until)
            ->where('allocated_until', '>', $from)
            ->pluck('rental_inventory_unit_id');

        return $item->inventoryUnits()
            ->whereIn('status', [RentalInventoryUnit::STATUS_AVAILABLE])
            ->whereNotIn('id', $reservedUnitIds)
            ->orderByDesc('preferred_for_booking')
            ->get();
    }
}
