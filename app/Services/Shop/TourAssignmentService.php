<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Delivery\DeliveryArea;
use App\Models\Delivery\RegularDeliveryTour;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PROJ-4: Resolve available delivery tours based on postal code and city.
 *
 * Used during checkout to determine which RegularDeliveryTours serve
 * the customer's delivery address. The result is filtered to active tours only.
 *
 * Resolution logic:
 *   1. Find all DeliveryAreas where postal_code matches exactly
 *   2. Optionally filter by city (LIKE match on city_name)
 *   3. Load the associated RegularDeliveryTour for each area
 *   4. Return only active tours allowed for the customer's group, sorted by name
 *
 * Returns:
 *   - 0 results: no tour covers this postal code (show warning in checkout)
 *   - 1 result:  auto-assign this tour
 *   - N results: let the customer choose
 */
class TourAssignmentService
{
    /**
     * Find tours that serve the given postal code and (optionally) city,
     * filtered to tours accessible by the given customer group.
     *
     * BUG-5 fix: added $customerGroupId parameter.
     * When PROJ-14 adds a customerGroups() pivot to RegularDeliveryTour,
     * this method will automatically filter by that relation.
     * Until then, all active tours in the postal code are returned.
     *
     * BUG-16 fix: LIKE wildcards in $city are escaped via addcslashes().
     *
     * @param  string    $postalCode       5-digit German postal code
     * @param  string    $city             City name for additional filtering
     * @param  int|null  $customerGroupId  Filter to tours allowed for this group
     * @return Collection<int, RegularDeliveryTour>
     */
    public function resolveTours(string $postalCode, string $city = '', ?int $customerGroupId = null): Collection
    {
        $query = DeliveryArea::where('postal_code', $postalCode);

        // If a city is provided, additionally filter by city_name LIKE match.
        if ($city !== '') {
            // BUG-16 fix: escape LIKE special characters to prevent wildcard injection.
            $escaped = addcslashes($city, '%_\\');
            $query->where(function ($q) use ($escaped): void {
                $q->whereNull('city_name')
                    ->orWhere('city_name', '')
                    ->orWhere('city_name', 'LIKE', '%' . $escaped . '%');
            });
        }

        $areas = $query->with('regularDeliveryTour')->get();

        $tours = $areas
            ->pluck('regularDeliveryTour')
            ->filter(fn (?RegularDeliveryTour $tour) => $tour !== null && $tour->active)
            ->unique('id')
            ->sortBy('name')
            ->values();

        // BUG-5 fix: filter by customer group if the tour model supports it.
        // When PROJ-14 adds a customerGroups() pivot, load it with eager loading
        // and replace this defensive check with a proper filter.
        if ($customerGroupId !== null) {
            $tours = $tours->filter(function (RegularDeliveryTour $tour) use ($customerGroupId): bool {
                if (method_exists($tour, 'customerGroups') && $tour->relationLoaded('customerGroups')) {
                    return $tour->customerGroups->contains('id', $customerGroupId);
                }
                // No group restriction schema yet — allow all groups.
                return true;
            });
        }

        $result = $tours->values();

        // BUG-7 fix: log when no tour covers the postal code — helps admin

        // identify gaps in the delivery area configuration (PROJ-14).
        if ($result->isEmpty()) {
            Log::info('TourAssignment: no delivery tour found for postal code', [
                'postal_code'       => $postalCode,
                'city'              => $city ?: null,
                'customer_group_id' => $customerGroupId,
            ]);
        }

        return $result;
    }

    /**
     * Return the next scheduled tour date for a regular delivery tour.
     *
     * Looks up ninox_liefer_tour for the earliest upcoming entry with
     * status "NEU und offen". Returns null if no date is available
     * (tour has no ninox_id, or no future runs planned).
     */
    public function nextTourDate(RegularDeliveryTour $tour): ?Carbon
    {
        if ($tour->ninox_id === null) {
            return null;
        }

        $row = DB::table('ninox_liefer_tour')
            ->where('regelmaessige_touren', $tour->ninox_id)
            ->where('datum', '>=', today()->toDateString())
            ->where('status', 'NEU und offen')
            ->orderBy('datum')
            ->first(['datum']);

        return $row ? Carbon::parse($row->datum) : null;
    }
}
