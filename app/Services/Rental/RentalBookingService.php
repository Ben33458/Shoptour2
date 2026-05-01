<?php

declare(strict_types=1);

namespace App\Services\Rental;

use App\Models\Pricing\Customer;
use App\Models\Orders\Order;
use App\Models\Rental\RentalBookingItem;
use App\Models\Rental\RentalTimeModel;
use App\Services\Orders\OrderNumberService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Erstellt Leih-Bestellungen aus dem Rental-Warenkorb.
 *
 * Jede Buchung ist eine normale Order mit is_event_order = true
 * sowie zugehörigen RentalBookingItems.
 */
class RentalBookingService
{
    public function __construct(
        private readonly OrderNumberService $orderNumbers,
        private readonly RentalAvailabilityService $availability,
        private readonly RentalPricingService $pricing,
    ) {}

    /**
     * Fügt Leihartikel einer bestehenden Bestellung hinzu und markiert sie als Veranstaltungsbestellung.
     *
     * @throws \RuntimeException wenn ein Artikel nicht mehr verfügbar ist
     */
    public function attachToOrder(Order $order, Customer $customer, array $cartData, array $eventData): void
    {
        $from      = Carbon::parse($cartData['date_from']);
        $until     = Carbon::parse($cartData['date_until']);
        $timeModel = RentalTimeModel::findOrFail(
            $cartData['time_model_id']
            ?? RentalTimeModel::where('default_for_events', true)->value('id')
        );

        DB::transaction(function () use ($order, $customer, $cartData, $eventData, $from, $until, $timeModel) {
            $rentalTotal = 0;

            foreach ($cartData['items'] ?? [] as $itemId => $row) {
                $rentalItem = \App\Models\Rental\RentalItem::findOrFail((int) $itemId);

                if (! $rentalItem->allow_overbooking) {
                    $avail = $this->availability->getAvailable($rentalItem, $from, $until, $row['qty']);
                    if ($avail < $row['qty']) {
                        throw new \RuntimeException(
                            "'{$rentalItem->name}' ist für den gewählten Zeitraum nicht mehr in der gewünschten Menge verfügbar."
                        );
                    }
                }

                $pricingResult = $this->pricing->calculateTotal(
                    $rentalItem,
                    $timeModel,
                    $row['qty'],
                    $row['packaging_unit_id'] ?? null,
                    $customer->customer_group_id ?? null,
                );

                $bookingItem = RentalBookingItem::create([
                    'company_id'             => $order->company_id,
                    'order_id'               => $order->id,
                    'rental_item_id'         => $rentalItem->id,
                    'packaging_unit_id'      => $row['packaging_unit_id'] ?? null,
                    'rental_time_model_id'   => $timeModel->id,
                    'quantity'               => $row['qty'],
                    'pieces_per_pack'        => $row['packaging_unit_id']
                        ? (\App\Models\Rental\RentalPackagingUnit::find($row['packaging_unit_id'])?->pieces_per_pack ?? 1)
                        : 1,
                    'total_pieces'           => $row['pieces'],
                    'unit_price_net_milli'   => $pricingResult['unit_price_net_milli'],
                    'total_price_net_milli'  => $pricingResult['total_price_net_milli'],
                    'status'                 => RentalBookingItem::STATUS_UNREVIEWED,
                ]);

                $rentalTotal += $pricingResult['total_price_net_milli'];

                if ($rentalItem->inventory_mode === \App\Models\Rental\RentalItem::MODE_UNIT) {
                    $units = $this->availability->getAvailableUnits($rentalItem, $from, $until, $row['qty']);
                    foreach ($units as $unit) {
                        \App\Models\Rental\RentalBookingAllocation::create([
                            'rental_booking_item_id'   => $bookingItem->id,
                            'rental_inventory_unit_id' => $unit->id,
                            'allocated_from'           => $from,
                            'allocated_until'          => $until,
                            'status'                   => 'reserved',
                        ]);
                    }
                }
            }

            $eventFields = array_intersect_key($eventData, array_flip([
                'event_location_name', 'event_location_street', 'event_location_zip',
                'event_location_city', 'event_contact_name', 'event_contact_phone',
                'event_delivery_mode', 'event_pickup_mode',
                'event_access_notes', 'event_setup_notes',
                'event_has_power', 'event_suitable_ground',
            ]));

            $order->update(array_merge($eventFields, [
                'is_event_order'        => true,
                'desired_delivery_date' => $from->toDateString(),
                'desired_pickup_date'   => $until->toDateString(),
                'total_net_milli'       => ($order->total_net_milli ?? 0) + $rentalTotal,
            ]));
        });
    }

    /**
     * Erstellt eine vollständige Leihbestellung inkl. aller Buchungspositionen.
     *
     * @param  Customer  $customer
     * @param  array     $cartData    Raw cart session data
     * @param  array     $eventData   Validated event/location fields
     * @return Order
     * @throws \RuntimeException wenn ein Artikel nicht mehr verfügbar ist
     */
    public function createFromCart(Customer $customer, array $cartData, array $eventData): Order
    {
        $from      = Carbon::parse($cartData['date_from']);
        $until     = Carbon::parse($cartData['date_until']);
        $timeModel = RentalTimeModel::findOrFail($cartData['time_model_id']
            ?? RentalTimeModel::where('default_for_events', true)->value('id'));

        return DB::transaction(function () use ($customer, $cartData, $eventData, $from, $until, $timeModel) {
            // 1. Build order
            $order = Order::create(array_merge($eventData, [
                'company_id'                  => $customer->company_id ?? 1,
                'order_number'               => $this->orderNumbers->generate(),
                'customer_id'                => $customer->id,
                'customer_group_id_snapshot' => $customer->customer_group_id,
                'status'                     => 'pending',
                'delivery_type'              => $eventData['event_delivery_mode'] === 'delivery' ? 'home_delivery' : 'pickup',
                'is_event_order'             => true,
                'desired_delivery_date'      => $from->toDateString(),
                'desired_pickup_date'        => $until->toDateString(),
                'total_net_milli'            => 0,
                'total_gross_milli'          => 0,
            ]));

            $orderTotal = 0;

            // 2. Create rental booking items
            foreach ($cartData['items'] ?? [] as $itemId => $row) {
                $rentalItem = \App\Models\Rental\RentalItem::findOrFail((int) $itemId);

                // Final availability check
                if (! $rentalItem->allow_overbooking) {
                    $avail = $this->availability->getAvailable($rentalItem, $from, $until, $row['qty']);
                    if ($avail < $row['qty']) {
                        throw new \RuntimeException(
                            "'{$rentalItem->name}' ist für den gewählten Zeitraum nicht mehr in der gewünschten Menge verfügbar."
                        );
                    }
                }

                $pricingResult = $this->pricing->calculateTotal(
                    $rentalItem,
                    $timeModel,
                    $row['qty'],
                    $row['packaging_unit_id'] ?? null,
                    $customer->customer_group_id ?? null,
                );

                $bookingItem = RentalBookingItem::create([
                    'company_id'              => $order->company_id,
                    'order_id'                => $order->id,
                    'rental_item_id'          => $rentalItem->id,
                    'packaging_unit_id'       => $row['packaging_unit_id'] ?? null,
                    'rental_time_model_id'    => $timeModel->id,
                    'quantity'                => $row['qty'],
                    'pieces_per_pack'         => $row['packaging_unit_id']
                        ? (\App\Models\Rental\RentalPackagingUnit::find($row['packaging_unit_id'])?->pieces_per_pack ?? 1)
                        : 1,
                    'total_pieces'            => $row['pieces'],
                    'unit_price_net_milli'    => $pricingResult['unit_price_net_milli'],
                    'total_price_net_milli'   => $pricingResult['total_price_net_milli'],
                    'status'                  => RentalBookingItem::STATUS_UNREVIEWED,
                ]);

                $orderTotal += $pricingResult['total_price_net_milli'];

                // 3. Create allocations for unit_based items
                if ($rentalItem->inventory_mode === \App\Models\Rental\RentalItem::MODE_UNIT) {
                    $units = $this->availability->getAvailableUnits($rentalItem, $from, $until, $row['qty']);
                    foreach ($units as $unit) {
                        \App\Models\Rental\RentalBookingAllocation::create([
                            'rental_booking_item_id'  => $bookingItem->id,
                            'rental_inventory_unit_id'=> $unit->id,
                            'allocated_from'          => $from,
                            'allocated_until'         => $until,
                            'status'                  => 'reserved',
                        ]);
                    }
                }
            }

            // 4. Update order total
            $order->update(['total_net_milli' => $orderTotal]);

            return $order;
        });
    }
}
