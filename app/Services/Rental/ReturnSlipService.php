<?php
declare(strict_types=1);
namespace App\Services\Rental;

use App\Models\Orders\Order;
use App\Models\Rental\DamageTariff;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalInventoryUnit;
use App\Models\Rental\RentalReturnSlip;
use App\Models\Rental\RentalReturnSlipItem;
use Illuminate\Support\Facades\DB;

/**
 * Service für Rückgabescheine.
 *
 * Rückgabeschein ist für alle Leihartikel PFLICHT.
 * Wird im Fahrer-Tool beim Abholen der Leihartikel erstellt.
 */
class ReturnSlipService
{
    /**
     * Erstellt einen neuen Rückgabeschein für einen Eventauftrag.
     * Wird automatisch beim ersten Rückgabevorgang erzeugt.
     */
    public function createForOrder(Order $order, ?int $driverUserId = null): RentalReturnSlip
    {
        return RentalReturnSlip::create([
            'company_id'     => $order->company_id,
            'order_id'       => $order->id,
            'driver_user_id' => $driverUserId,
            'status'         => RentalReturnSlip::STATUS_OPEN,
        ]);
    }

    /**
     * Erfasst Rückgabe einer Position.
     * Berechnet Schadenstarif automatisch.
     * Admin/Fahrer können manuell übersteuern.
     */
    public function recordReturn(
        RentalReturnSlip $slip,
        RentalBookingItem $bookingItem,
        int $returnedQuantity,
        string $cleanStatus,
        string $damageStatus,
        ?string $notes = null,
        ?string $photoPath = null,
        ?int $manualExtraChargeMilli = null,
    ): RentalReturnSlipItem {
        return DB::transaction(function () use (
            $slip, $bookingItem, $returnedQuantity, $cleanStatus,
            $damageStatus, $notes, $photoPath, $manualExtraChargeMilli
        ) {
            // Lookup damage tariff
            $damageTariff  = null;
            $suggestedMilli = 0;

            if ($damageStatus !== 'none') {
                $damageTariff = $this->findDamageTariff($bookingItem);
                if ($damageTariff) {
                    $suggestedMilli = $damageTariff->amount_net_milli * $returnedQuantity;
                }
            }

            $slipItem = RentalReturnSlipItem::create([
                'rental_return_slip_id'       => $slip->id,
                'rental_booking_item_id'      => $bookingItem->id,
                'returned_quantity'           => $returnedQuantity,
                'clean_status'               => $cleanStatus,
                'damage_status'              => $damageStatus,
                'damage_tariff_id'           => $damageTariff?->id,
                'suggested_extra_charge_milli' => $suggestedMilli,
                'manual_extra_charge_milli'   => $manualExtraChargeMilli,
                'notes'                      => $notes,
                'photo_path'                 => $photoPath,
            ]);

            // Update booking item status
            $bookingItem->update(['status' => RentalBookingItem::STATUS_RETURNED]);

            // Update inventory unit status based on damage
            $this->updateInventoryUnitStatus($bookingItem, $damageStatus);

            // Recalculate slip status
            $this->recalculateSlipStatus($slip);

            return $slipItem;
        });
    }

    private function findDamageTariff(RentalBookingItem $bookingItem): ?DamageTariff
    {
        $item = $bookingItem->rentalItem;

        // Look for tariff on specific item first, then category
        return DamageTariff::where('active', true)
            ->where(function ($q) use ($item, $bookingItem) {
                $q->where(fn($s) => $s->where('applies_to_type', 'rental_item')->where('applies_to_id', $item->id))
                  ->orWhere(fn($s) => $s->where('applies_to_type', 'category')->where('applies_to_id', $item->category_id))
                  ->orWhere(fn($s) => $s->where('applies_to_type', 'packaging_unit')->where('applies_to_id', $bookingItem->packaging_unit_id));
            })
            ->orderByRaw("CASE applies_to_type WHEN 'rental_item' THEN 0 WHEN 'packaging_unit' THEN 1 ELSE 2 END")
            ->first();
    }

    private function updateInventoryUnitStatus(RentalBookingItem $item, string $damageStatus): void
    {
        $unit = $item->fixedInventoryUnit ?? $item->desiredInventoryUnit;
        if (!$unit) return;

        $newStatus = match($damageStatus) {
            'damaged', 'not_rentable' => RentalInventoryUnit::STATUS_DEFECTIVE,
            'damaged_but_still_rentable' => RentalInventoryUnit::STATUS_AVAILABLE,
            default => RentalInventoryUnit::STATUS_AVAILABLE,
        };

        $unit->update(['status' => $newStatus]);
    }

    private function recalculateSlipStatus(RentalReturnSlip $slip): void
    {
        $slip->refresh();
        $totalItems    = $slip->order->rentalBookingItems()->whereIn('status', ['delivered', 'returned'])->count();
        $returnedItems = $slip->items()->count();

        $newStatus = match(true) {
            $returnedItems === 0              => RentalReturnSlip::STATUS_OPEN,
            $returnedItems < $totalItems      => RentalReturnSlip::STATUS_PARTIAL,
            default                           => RentalReturnSlip::STATUS_COMPLETE,
        };

        $slip->update(['status' => $newStatus]);
    }
}
