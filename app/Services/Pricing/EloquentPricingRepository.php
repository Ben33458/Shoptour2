<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Pricing\AppSetting;
use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\CustomerGroupPrice;
use App\Models\Pricing\CustomerPrice;
use Illuminate\Support\Facades\DB;

/**
 * Production implementation of PricingRepositoryInterface backed by Eloquent / MySQL.
 *
 * BUG-3 fix: valid_from / valid_to windows are now evaluated.
 * A price row is "active" when:
 *   - valid_from IS NULL  OR  valid_from <= now()
 *   - valid_to   IS NULL  OR  valid_to   >= now()
 *
 * When multiple rows are valid for the same (customer_id, product_id) at the
 * same time (possible when the UNIQUE index allows NULL valid_from), the row
 * with the latest valid_from is preferred (most-recently-started window wins).
 */
class EloquentPricingRepository implements PricingRepositoryInterface
{
    public function getSettingInt(string $key): int
    {
        return AppSetting::getInt($key);
    }

    public function findGroup(int $id): ?CustomerGroup
    {
        return CustomerGroup::find($id);
    }

    public function findValidCustomerPrice(int $customerId, int $productId): ?CustomerPrice
    {
        $now = now();

        return CustomerPrice::query()
            ->where('customer_id', $customerId)
            ->where('product_id',  $productId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('valid_to')  ->orWhere('valid_to',   '>=', $now))
            ->orderByDesc('valid_from')
            ->first();
    }

    public function findValidGroupPrice(int $groupId, int $productId): ?CustomerGroupPrice
    {
        $now = now();

        return CustomerGroupPrice::query()
            ->where('customer_group_id', $groupId)
            ->where('product_id',        $productId)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now))
            ->where(fn ($q) => $q->whereNull('valid_to')  ->orWhere('valid_to',   '>=', $now))
            ->orderByDesc('valid_from')
            ->first();
    }

    public function getTaxRateBasisPointsForProduct(int $productId): int
    {
        $row = DB::table('products')
            ->join('tax_rates', 'products.tax_rate_id', '=', 'tax_rates.id')
            ->where('products.id', $productId)
            ->value('tax_rates.rate_basis_points');

        if ($row === null) {
            throw new \RuntimeException(
                "Cannot resolve tax rate for product #{$productId}: "
                . "product not found or tax_rate_id points to a missing tax_rates row."
            );
        }

        return (int) $row;
    }
}
