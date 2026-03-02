<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Pricing\CustomerGroup;
use App\Models\Pricing\CustomerGroupPrice;
use App\Models\Pricing\CustomerPrice;

/**
 * Abstracts all database read operations required by PriceResolverService.
 *
 * Keeping queries behind this interface lets us:
 *   • Unit-test the service without a database (swap with a stub/mock)
 *   • Swap the persistence backend in the future without touching business logic
 */
interface PricingRepositoryInterface
{
    /**
     * Return the integer value of an app_setting by key.
     *
     * @throws \RuntimeException when the key is missing.
     */
    public function getSettingInt(string $key): int;

    /**
     * Load a CustomerGroup by primary key, or return null when not found.
     */
    public function findGroup(int $id): ?CustomerGroup;

    /**
     * Find the active CustomerPrice for customer+product.
     * Returns null when no row exists.
     * Exactly one row per (customer_id, product_id) is guaranteed by the UNIQUE index.
     */
    public function findValidCustomerPrice(int $customerId, int $productId): ?CustomerPrice;

    /**
     * Find the active CustomerGroupPrice for group+product.
     * Returns null when no row exists.
     * Exactly one row per (customer_group_id, product_id) is guaranteed by the UNIQUE index.
     */
    public function findValidGroupPrice(int $groupId, int $productId): ?CustomerGroupPrice;

    /**
     * Return the tax rate in basis points for the given product.
     *
     * Follows the join:  products.tax_rate_id → tax_rates.rate_basis_points
     *
     * @throws \RuntimeException when the product does not exist or its tax_rate relation is missing.
     */
    public function getTaxRateBasisPointsForProduct(int $productId): int;
}
