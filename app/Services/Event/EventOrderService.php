<?php
declare(strict_types=1);
namespace App\Services\Event;

use App\Models\Event\EventLocation;
use App\Models\Orders\Order;
use App\Models\Rental\RentalBookingAllocation;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalReturnSlip;
use App\Services\Rental\RentalAvailabilityService;
use App\Services\Rental\RentalPricingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Event-spezifische Auftragslogik.
 *
 * Festbedarf NUR bei Eventauftrag buchbar.
 * 50% Vorkasse auf Miete, Getränke, Service, Lieferkosten (OHNE Pfand).
 * Zahlungsfrist: 7 Tage vor Event.
 * Lieferzeit-Wunschfenster: mindestens 2 Stunden.
 */
class EventOrderService
{
    public function __construct(
        private readonly RentalAvailabilityService $availability,
        private readonly RentalPricingService $pricing,
        private readonly LogisticsClassService $logistics,
    ) {}

    /**
     * Markiert eine Bestellung als Eventauftrag und setzt Event-Felder.
     */
    public function convertToEventOrder(Order $order, array $eventData): Order
    {
        $this->validateDeliveryWindow($eventData);

        $prepaymentDue = null;
        if (!empty($eventData['desired_delivery_date'])) {
            $prepaymentDue = Carbon::parse($eventData['desired_delivery_date'])
                ->subDays(7)
                ->toDateString();
        }

        $order->update([
            'is_event_order'              => true,
            'event_location_id'           => $eventData['event_location_id'] ?? null,
            'event_location_name'         => $eventData['event_location_name'] ?? null,
            'event_location_street'       => $eventData['event_location_street'] ?? null,
            'event_location_zip'          => $eventData['event_location_zip'] ?? null,
            'event_location_city'         => $eventData['event_location_city'] ?? null,
            'event_contact_name'          => $eventData['event_contact_name'] ?? null,
            'event_contact_phone'         => $eventData['event_contact_phone'] ?? null,
            'event_access_notes'          => $eventData['event_access_notes'] ?? null,
            'event_setup_notes'           => $eventData['event_setup_notes'] ?? null,
            'event_has_power'             => (bool) ($eventData['event_has_power'] ?? false),
            'event_suitable_ground'       => (bool) ($eventData['event_suitable_ground'] ?? true),
            'desired_delivery_date'       => $eventData['desired_delivery_date'] ?? null,
            'desired_delivery_time_from'  => $eventData['desired_delivery_time_from'] ?? null,
            'desired_delivery_time_to'    => $eventData['desired_delivery_time_to'] ?? null,
            'desired_pickup_date'         => $eventData['desired_pickup_date'] ?? null,
            'desired_pickup_time_from'    => $eventData['desired_pickup_time_from'] ?? null,
            'desired_pickup_time_to'      => $eventData['desired_pickup_time_to'] ?? null,
            'event_delivery_mode'         => $eventData['event_delivery_mode'] ?? 'delivery',
            'event_pickup_mode'           => $eventData['event_pickup_mode'] ?? 'pickup_by_us',
            'prepayment_due_date'         => $prepaymentDue,
        ]);

        return $order;
    }

    /**
     * Fügt eine Mietposition zum Eventauftrag hinzu.
     * Prüft Verfügbarkeit, blockiert sofort.
     */
    public function addRentalBookingItem(
        Order $order,
        array $bookingData,
        Carbon $deliveryDate,
        Carbon $pickupDate,
    ): RentalBookingItem {
        if (!$order->is_event_order) {
            throw new \DomainException('Festbedarf darf nur im Kontext eines Eventauftrags gebucht werden.');
        }

        return DB::transaction(function () use ($order, $bookingData, $deliveryDate, $pickupDate) {
            $rentalItemId  = $bookingData['rental_item_id'];
            $timeModelId   = $bookingData['rental_time_model_id'];
            $quantity      = $bookingData['quantity'];
            $packagingUnitId = $bookingData['packaging_unit_id'] ?? null;

            $rentalItem = \App\Models\Rental\RentalItem::findOrFail($rentalItemId);
            $timeModel  = \App\Models\Rental\RentalTimeModel::findOrFail($timeModelId);

            // Check availability
            if (!$this->availability->canBook($rentalItem, $deliveryDate, $pickupDate, $quantity)) {
                throw new \DomainException("Mietartikel '{$rentalItem->name}' ist im gewünschten Zeitraum nicht verfügbar.");
            }

            // Resolve price
            $priceData = $this->pricing->calculateTotal(
                $rentalItem, $timeModel, $quantity, $packagingUnitId,
                $order->customer->customer_group_id ?? null,
            );

            $bookingItem = RentalBookingItem::create([
                'company_id'             => $order->company_id,
                'order_id'               => $order->id,
                'rental_item_id'         => $rentalItemId,
                'packaging_unit_id'      => $packagingUnitId,
                'rental_time_model_id'   => $timeModelId,
                'quantity'               => $quantity,
                'pieces_per_pack'        => $bookingData['pieces_per_pack'] ?? null,
                'total_pieces'           => ($bookingData['pieces_per_pack'] ?? 1) * $quantity,
                'unit_price_net_milli'   => $priceData['unit_price_net_milli'],
                'total_price_net_milli'  => $priceData['total_price_net_milli'],
                'desired_specific_inventory_unit_id' => $bookingData['desired_unit_id'] ?? null,
                'status'                 => RentalBookingItem::STATUS_UNREVIEWED,
                'notes'                  => $bookingData['notes'] ?? null,
            ]);

            // For unit_based: create allocation immediately
            if ($rentalItem->isUnitBased()) {
                $unitId = $bookingData['desired_unit_id'] ?? $this->availability->getAvailableUnits($rentalItem, $deliveryDate, $pickupDate)->first()?->id;
                if ($unitId) {
                    RentalBookingAllocation::create([
                        'rental_booking_item_id'   => $bookingItem->id,
                        'rental_inventory_unit_id' => $unitId,
                        'allocated_from'           => $deliveryDate,
                        'allocated_until'          => $pickupDate,
                        'status'                   => 'reserved',
                    ]);
                }
            }

            // Recalculate logistics class
            $this->updateLogisticsClass($order);

            return $bookingItem;
        });
    }

    /**
     * Berechnet Vorkasse: 50% auf Miete, Getränke, Service, Lieferkosten (OHNE Pfand).
     */
    public function calculatePrepayment(Order $order): int
    {
        $rentalTotal = $order->rentalBookingItems()
            ->whereIn('status', ['unreviewed', 'reserved', 'confirmed'])
            ->sum('total_price_net_milli');

        // 50% of rental total (Getränke/Service would be in order items)
        $regularTotal = $order->total_net_milli - $order->total_pfand_brutto_milli;
        $prepaymentBase = $rentalTotal + $regularTotal;

        return (int) round($prepaymentBase * 0.5);
    }

    /**
     * Erstellt Rückgabeschein für Eventauftrag falls noch nicht vorhanden.
     */
    public function ensureReturnSlip(Order $order, ?int $driverUserId = null): RentalReturnSlip
    {
        return RentalReturnSlip::firstOrCreate(
            ['order_id' => $order->id],
            [
                'company_id'     => $order->company_id,
                'driver_user_id' => $driverUserId,
                'status'         => RentalReturnSlip::STATUS_OPEN,
            ]
        );
    }

    private function validateDeliveryWindow(array $data): void
    {
        if (!empty($data['desired_delivery_time_from']) && !empty($data['desired_delivery_time_to'])) {
            $from = Carbon::createFromFormat('H:i', $data['desired_delivery_time_from']);
            $to   = Carbon::createFromFormat('H:i', $data['desired_delivery_time_to']);
            if ($to->diffInMinutes($from) < 120) {
                throw new \InvalidArgumentException('Wunsch-Zeitfenster für Lieferung muss mindestens 2 Stunden betragen.');
            }
        }
        if (!empty($data['desired_pickup_time_from']) && !empty($data['desired_pickup_time_to'])) {
            $from = Carbon::createFromFormat('H:i', $data['desired_pickup_time_from']);
            $to   = Carbon::createFromFormat('H:i', $data['desired_pickup_time_to']);
            if ($to->diffInMinutes($from) < 120) {
                throw new \InvalidArgumentException('Wunsch-Zeitfenster für Abholung muss mindestens 2 Stunden betragen.');
            }
        }
    }

    private function updateLogisticsClass(Order $order): void
    {
        $class = $this->logistics->calculateForOrder($order);
        $order->update(['logistics_class' => $class]);
    }
}
