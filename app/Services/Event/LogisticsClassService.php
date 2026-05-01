<?php
declare(strict_types=1);
namespace App\Services\Event;

use App\Models\Orders\Order;
use App\Models\Rental\RentalItem;

/**
 * Bestimmt automatisch die Logistikklasse eines Eventauftrags.
 *
 * Regel:
 *   - enthält mindestens ein truck-Artikel => truck
 *   - sonst mindestens ein normal-Artikel  => normal
 *   - sonst small
 *
 * Anfahrtspauschalen:
 *   small  = 10 €
 *   normal = 20 €
 *   truck  = 30 €
 */
class LogisticsClassService
{
    public const SURCHARGE_SMALL  = 10_000_000;  // 10 EUR in milli-cents
    public const SURCHARGE_NORMAL = 20_000_000;  // 20 EUR in milli-cents
    public const SURCHARGE_TRUCK  = 30_000_000;  // 30 EUR in milli-cents

    /**
     * Berechnet die Logistikklasse aus den Buchungspositionen.
     */
    public function calculateForOrder(Order $order): string
    {
        $items = $order->rentalBookingItems()
            ->whereIn('status', ['unreviewed', 'reserved', 'confirmed', 'delivered'])
            ->with('rentalItem')
            ->get();

        return $this->calculateFromItems($items->pluck('rentalItem'));
    }

    /**
     * Berechnet die Logistikklasse aus einer Liste von RentalItems.
     */
    public function calculateFromItems(\Illuminate\Support\Collection $rentalItems): string
    {
        $classes = $rentalItems->pluck('transport_class')->unique()->toArray();

        if (in_array(RentalItem::TRANSPORT_TRUCK, $classes)) {
            return RentalItem::TRANSPORT_TRUCK;
        }
        if (in_array(RentalItem::TRANSPORT_NORMAL, $classes)) {
            return RentalItem::TRANSPORT_NORMAL;
        }
        return RentalItem::TRANSPORT_SMALL;
    }

    /**
     * Gibt die Anfahrtspauschale für eine Logistikklasse in Milli-Cent zurück.
     */
    public function getSurchargeMilli(string $class): int
    {
        return match($class) {
            RentalItem::TRANSPORT_TRUCK  => self::SURCHARGE_TRUCK,
            RentalItem::TRANSPORT_NORMAL => self::SURCHARGE_NORMAL,
            default                       => self::SURCHARGE_SMALL,
        };
    }
}
