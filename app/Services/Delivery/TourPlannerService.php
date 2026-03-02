<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Models\Delivery\CustomerTourOrder;
use App\Models\Delivery\RegularDeliveryTour;
use App\Models\Delivery\Tour;
use App\Models\Delivery\TourStop;
use App\Models\Orders\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds concrete Tour runs from a RegularDeliveryTour template.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │                  createTourForDate()                        │
 * │                                                             │
 * │  1. Find all eligible orders:                               │
 * │       orders.delivery_date = $date                          │
 * │       AND orders.regular_delivery_tour_id = $regularTourId  │
 * │       AND orders.status NOT IN ('cancelled')                │
 * │                                                             │
 * │  2. Sort orders into stop sequence:                         │
 * │       a) Look up customer_tour_orders.stop_order_number     │
 * │       b) Orders with a defined stop_order come first        │
 * │          (ascending by stop_order_number)                   │
 * │       c) Orders without a stop_order fall back to sorting   │
 * │          by customer.last_name (then first_name)            │
 * │                                                             │
 * │  3. Create one Tour row.                                    │
 * │  4. Create one TourStop row per order (stop_index = 1-based │
 * │     position in the sorted list).                           │
 * │                                                             │
 * │  All writes are inside a DB transaction.                    │
 * └─────────────────────────────────────────────────────────────┘
 */
class TourPlannerService
{
    /**
     * Create a Tour for a given date and regular tour template.
     *
     * @param  Carbon $date
     * @param  int    $regularDeliveryTourId
     * @return Tour                           The newly created Tour with stops loaded
     *
     * @throws \RuntimeException when the RegularDeliveryTour does not exist
     * @throws \RuntimeException when no eligible orders are found for this date/tour
     */
    public function createTourForDate(Carbon $date, int $regularDeliveryTourId): Tour
    {
        $regularTour = RegularDeliveryTour::find($regularDeliveryTourId);

        if ($regularTour === null) {
            throw new \RuntimeException(
                "RegularDeliveryTour #{$regularDeliveryTourId} does not exist."
            );
        }

        return DB::transaction(function () use ($date, $regularTour): Tour {
            // ------------------------------------------------------------------
            // 1. Find eligible orders for this date + tour combination
            // ------------------------------------------------------------------
            $orders = Order::query()
                ->with(['customer'])
                ->whereDate('delivery_date', $date->toDateString())
                ->where('regular_delivery_tour_id', $regularTour->id)
                ->whereNotIn('status', [Order::STATUS_CANCELLED])
                ->get();

            if ($orders->isEmpty()) {
                throw new \RuntimeException(
                    "No eligible orders found for tour #{$regularTour->id} "
                    . "on {$date->toDateString()}."
                );
            }

            // ------------------------------------------------------------------
            // 2. Load stop-order numbers for all relevant customers in one query
            // ------------------------------------------------------------------
            $customerIds = $orders->pluck('customer_id')->unique()->values();

            // Map: customer_id → stop_order_number (null if no entry)
            $stopOrders = CustomerTourOrder::query()
                ->where('regular_delivery_tour_id', $regularTour->id)
                ->whereIn('customer_id', $customerIds)
                ->pluck('stop_order_number', 'customer_id');

            // ------------------------------------------------------------------
            // 3. Sort orders:
            //    - Orders WITH a stop_order_number sort by that number (asc)
            //    - Orders WITHOUT fall after all ordered ones, sorted by customer name
            // ------------------------------------------------------------------
            $sorted = $orders->sortBy(function (Order $order) use ($stopOrders): array {
                $stopNumber = $stopOrders->get($order->customer_id);
                $customer   = $order->customer;

                $lastName  = $customer?->last_name  ?? '';
                $firstName = $customer?->first_name ?? '';

                if ($stopNumber !== null) {
                    // Defined stop order: sort first group by number
                    return [0, $stopNumber, $lastName, $firstName];
                }

                // No stop order: falls into second group, sorted alphabetically
                return [1, 0, $lastName, $firstName];
            })->values();

            // ------------------------------------------------------------------
            // 4. Create Tour header
            // ------------------------------------------------------------------
            $tour = Tour::create([
                'tour_date'                 => $date->toDateString(),
                'regular_delivery_tour_id'  => $regularTour->id,
                'driver_employee_id'        => null,
                'status'                    => Tour::STATUS_PLANNED,
            ]);

            // ------------------------------------------------------------------
            // 5. Create TourStop per order (1-based stop_index)
            // ------------------------------------------------------------------
            foreach ($sorted as $index => $order) {
                TourStop::create([
                    'tour_id'    => $tour->id,
                    'order_id'   => $order->id,
                    'stop_index' => $index + 1,
                    'status'     => TourStop::STATUS_OPEN,
                ]);
            }

            // Return tour with stops eagerly loaded in correct sequence
            return $tour->load('stops');
        });
    }
}
