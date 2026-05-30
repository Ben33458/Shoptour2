<?php

declare(strict_types=1);

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\Pricing\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/driver/customers?q=<text>
 *
 * Search for active customers by name, customer number, or email.
 * Used by the driver PWA to add ad-hoc stops during a tour.
 */
class DriverCustomerSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $customers = Customer::where('active', true)
            ->where(function ($query) use ($q): void {
                $like = '%' . $q . '%';
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('customer_number', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);
            })
            ->with('defaultDeliveryAddress')
            ->orderBy('customer_number')
            ->limit(15)
            ->get();

        return response()->json(
            $customers->map(fn (Customer $c) => [
                'id'              => $c->id,
                'customer_number' => $c->customer_number,
                'display_name'    => $c->displayName(),
                'city'            => $c->defaultDeliveryAddress?->city,
            ])->values()
        );
    }
}
