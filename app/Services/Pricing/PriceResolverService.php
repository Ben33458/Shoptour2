<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\DTOs\Pricing\PriceResult;
use App\Models\Catalog\Product;
use App\Models\Pricing\Customer;
use App\Models\Pricing\CustomerGroup;

/**
 * Core pricing engine for Kolabri Getränke.
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │                     PRICE RESOLUTION ORDER                      │
 * ├───┬─────────────────────────────────────────────────────────────┤
 * │ 1 │ customer_prices       (customer-specific, one per customer)  │
 * │ 2 │ customer_group_prices (group-wide,        one per group)     │
 * │ 3 │ base_price + customer_group.adjustment                       │
 * └───┴─────────────────────────────────────────────────────────────┘
 *
 * Rules:
 *  - Guest sessions use the customer group stored in
 *    app_settings["default_customer_group_id"].
 *  - An explicit customer_price is returned as-is; no group adjustment applied.
 *  - A customer_group_price is returned as-is; no group adjustment applied.
 *  - Group adjustments (fixed / percent) are applied ONLY on path (3).
 *  - The tax rate is resolved via PricingRepositoryInterface::getTaxRateBasisPointsForProduct(),
 *    which follows products.tax_rate_id → tax_rates.rate_basis_points.
 *    Callers do NOT need to eager-load any relations on the Product.
 *  - Gross is ALWAYS derived from net via the product's tax rate.
 *    Neither customer_prices nor customer_group_prices store a gross column.
 *
 * Monetary convention:
 *  All values are in milli-cents (int). 1 EUR = 1_000_000 milli-cents.
 *
 * The service is completely stateless and depends only on PricingRepositoryInterface,
 * making it trivially unit-testable without a database.
 */
class PriceResolverService
{
    public function __construct(
        private readonly PricingRepositoryInterface $repo,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Resolve the price for an unauthenticated (guest) session.
     *
     * The tax rate is fetched via the repository:
     *   products.tax_rate_id → tax_rates.rate_basis_points
     *
     * @param  Product $product
     * @return PriceResult
     * @throws \RuntimeException when default_customer_group_id is not configured
     * @throws \RuntimeException when the configured group does not exist
     * @throws \RuntimeException when the product has no associated tax rate
     */
    public function resolveForGuest(Product $product): PriceResult
    {
        $groupId = $this->repo->getSettingInt('default_customer_group_id');
        $group   = $this->findGroupOrFail($groupId);
        $taxBp   = $this->repo->getTaxRateBasisPointsForProduct($product->id);

        return $this->resolveForGroup(
            product: $product,
            group:   $group,
            isGuest: true,
            taxBp:   $taxBp,
        );
    }

    /**
     * Resolve the price for an authenticated customer.
     *
     * The tax rate is fetched via the repository:
     *   products.tax_rate_id → tax_rates.rate_basis_points
     *
     * @param  Product  $product
     * @param  Customer $customer
     * @return PriceResult
     * @throws \RuntimeException when the product has no associated tax rate
     */
    public function resolveForCustomer(Product $product, Customer $customer): PriceResult
    {
        $group = $customer->customerGroup ?? $this->findGroupOrFail($customer->customer_group_id);
        $taxBp = $this->repo->getTaxRateBasisPointsForProduct($product->id);

        // Priority 1: customer-specific price
        $customerPrice = $this->repo->findValidCustomerPrice($customer->id, $product->id);

        if ($customerPrice !== null) {
            $netMilli = $customerPrice->price_net_milli;

            return new PriceResult(
                netMilli:        $netMilli,
                grossMilli:      $this->resolveNetToGross($netMilli, $taxBp),
                source:          PriceResult::SOURCE_CUSTOMER_PRICE,
                isGuest:         false,
                customerGroupId: $group->id,
            );
        }

        // Priorities 2 & 3: group-level and base
        return $this->resolveForGroup(
            product: $product,
            group:   $group,
            isGuest: false,
            taxBp:   $taxBp,
        );
    }

    /**
     * Convert a net milli-cent amount to gross using tax_rate_basis_points.
     *
     * Pure integer arithmetic — no floats, no IEEE-754 rounding drift.
     *
     * Tax-rate scale: 10_000 bp = 100 %  (matches tax_rates.rate_basis_points).
     *   1_900 = 19 % German standard VAT
     *     700 =  7 % German reduced VAT
     *
     * Formula (exact):
     *   gross = round_half_up( netMilli × (10_000 + taxRateBasisPoints) / 10_000 )
     *
     * Half-up is implemented without float by adding half the divisor
     * (sign-aware) before integer division:
     *
     *   numerator = netMilli × (10_000 + taxRateBasisPoints)
     *   gross     = intdiv( numerator + sign(numerator) × 5_000 , 10_000 )
     *
     * Where sign(numerator) = +1 for ≥ 0, −1 for < 0.
     * This is correct for netMilli = 0 and for negative net amounts.
     *
     * Overflow safety: PHP integers are 64-bit signed on all supported
     * platforms (max ~9.2 × 10^18). The largest realistic numerator
     * (netMilli ≈ 10^10, taxBp = 1_900 → factor 11_900) is ~1.2 × 10^14,
     * well within the 64-bit range.
     *
     * NOTE: price_adjustment_percent_basis_points on CustomerGroup uses a
     * DIFFERENT scale (1_000_000 = 100 %) — handled in applyGroupAdjustment().
     *
     * @param  int $netMilli            Net price in milli-cents
     * @param  int $taxRateBasisPoints  e.g. 1_900 = 19 % VAT  (scale: 10_000 = 100 %)
     * @return int                      Gross price in milli-cents, half-up rounded
     */
    public function resolveNetToGross(int $netMilli, int $taxRateBasisPoints): int
    {
        // BUG-1 fix: tax_rates scale is 10_000 = 100% (1_900 = 19%, 700 = 7%).
        $numerator = $netMilli * (10_000 + $taxRateBasisPoints);
        $half      = $numerator >= 0 ? 5_000 : -5_000;

        return intdiv($numerator + $half, 10_000);
    }

    // =========================================================================
    // Internal resolution
    // =========================================================================

    private function resolveForGroup(
        Product       $product,
        CustomerGroup $group,
        bool          $isGuest,
        int           $taxBp,
    ): PriceResult {
        // Priority 2: customer_group_price
        $groupPrice = $this->repo->findValidGroupPrice($group->id, $product->id);

        if ($groupPrice !== null) {
            $netMilli = $groupPrice->price_net_milli;

            return new PriceResult(
                netMilli:        $netMilli,
                grossMilli:      $this->resolveNetToGross($netMilli, $taxBp),
                source:          PriceResult::SOURCE_GROUP_PRICE,
                isGuest:         $isGuest,
                customerGroupId: $group->id,
            );
        }

        // Priority 3: base_price + group adjustment
        // BUG-5 fix: a product with no base price must not be sold silently for 0.
        $baseNetMilli = $product->base_price_net_milli;

        if ($baseNetMilli <= 0) {
            throw new \RuntimeException(
                "Product #{$product->id} has no base price configured "
                . "(base_price_net_milli = {$baseNetMilli}). "
                . "Set a base price or create an explicit customer / group price."
            );
        }

        $netMilli = $this->applyGroupAdjustment($baseNetMilli, $group);

        return new PriceResult(
            netMilli:        $netMilli,
            grossMilli:      $this->resolveNetToGross($netMilli, $taxBp),
            source:          PriceResult::SOURCE_BASE_PLUS_ADJUSTMENT,
            isGuest:         $isGuest,
            customerGroupId: $group->id,
        );
    }

    /**
     * Apply the customer group adjustment to a base net amount.
     *
     * "none"    → unchanged
     * "fixed"   → base + price_adjustment_fixed_milli   (signed)
     * "percent" → base × (1_000_000 + bp) / 1_000_000, rounded half-up (integer)
     *             Note: price_adjustment_percent_basis_points uses scale 1_000_000 = 100 %
     *             (different from tax_rate_basis_points which uses 10_000 = 100 %).
     *
     * BUG-2 fix: result is clamped to 0 so that a large discount cannot
     * produce a negative net price that flows into orders and invoices.
     *
     * The percent branch uses the same sign-aware intdiv half-up pattern as
     * resolveNetToGross() to avoid float arithmetic entirely.
     */
    private function applyGroupAdjustment(int $baseNetMilli, CustomerGroup $group): int
    {
        $adjusted = match ($group->price_adjustment_type) {
            CustomerGroup::ADJUSTMENT_FIXED =>
                $baseNetMilli + $group->price_adjustment_fixed_milli,

            CustomerGroup::ADJUSTMENT_PERCENT =>
                $this->intHalfUp(
                    $baseNetMilli * (1_000_000 + $group->price_adjustment_percent_basis_points),
                    1_000_000,
                ),

            default => $baseNetMilli,
        };

        // BUG-2 fix: clamp to 0 — a discount cannot produce a negative net price.
        return max(0, $adjusted);
    }

    /**
     * Integer half-up division: round_half_up(numerator / divisor).
     *
     * Divisor must be positive. Sign of result follows sign of numerator.
     */
    private function intHalfUp(int $numerator, int $divisor): int
    {
        $half = $numerator >= 0 ? intdiv($divisor, 2) : -intdiv($divisor, 2);

        return intdiv($numerator + $half, $divisor);
    }

    /**
     * @throws \RuntimeException
     */
    private function findGroupOrFail(int $groupId): CustomerGroup
    {
        $group = $this->repo->findGroup($groupId);

        if ($group === null) {
            throw new \RuntimeException(
                "CustomerGroup #{$groupId} not found. "
                . "Ensure default_customer_group_id in app_settings points to a valid group."
            );
        }

        return $group;
    }
}
