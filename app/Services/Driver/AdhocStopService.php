<?php

declare(strict_types=1);

namespace App\Services\Driver;

use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Orders\Order;
use App\Models\Pricing\Customer;

class AdhocStopService
{
    /**
     * Add an ad-hoc customer stop to an active tour.
     *
     * Checks for an existing open order for this customer on the tour date
     * before creating a new one (prevents duplicate orders).
     *
     * @return array{stop: TourStop, reused_existing_order: bool, order_id: int}
     */
    public function createStop(Tour $tour, Customer $customer, ?string $note): array
    {
        // Try to reuse an existing open order for this customer on the same day
        $existingOrder = Order::where('customer_id', $customer->id)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_CONFIRMED])
            ->whereDate('created_at', $tour->tour_date)
            ->whereDoesntHave('tourStop')
            ->latest()
            ->first();

        $reusingExisting = $existingOrder !== null;

        if ($reusingExisting) {
            $order = $existingOrder;
        } else {
            $order = Order::create([
                'customer_id'                => $customer->id,
                'customer_group_id_snapshot' => $customer->customer_group_id,
                'status'                     => Order::STATUS_PENDING,
                'delivery_type'              => 'home_delivery',
                'payment_method'             => 'cash',
                'total_net_milli'            => 0,
                'total_gross_milli'          => 0,
                'total_pfand_brutto_milli'   => 0,
                'notes'                      => trim('[Ad-hoc Fahrerstopp] ' . ($note ?? '')),
            ]);
        }

        $nextIndex = ($tour->stops()->max('stop_index') ?? 0) + 1;

        $stop = TourStop::create([
            'tour_id'    => $tour->id,
            'order_id'   => $order->id,
            'stop_index' => $nextIndex,
            'status'     => 'open',
        ]);

        $stop->load(['order.customer.customerGroup', 'order.items', 'itemFulfillments', 'events']);

        return [
            'stop'                   => $stop,
            'reused_existing_order'  => $reusingExisting,
            'order_id'               => $order->id,
        ];
    }
}
