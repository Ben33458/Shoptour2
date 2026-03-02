<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\DTOs\Orders\OrderItemPricingSnapshot;
use App\Models\Catalog\Product;
use App\Models\Pricing\Customer;
use App\Services\Pricing\PricingRepositoryInterface;
use App\Services\Pricing\PriceResolverService;
use RuntimeException;

/**
 * Computes the complete pricing snapshot for a single order-item line.
 *
 * Responsibilities:
 *   1. Resolve the net/gross unit price via PriceResolverService
 *      (customer-specific → group → base+adjustment cascade).
 *   2. Snapshot the tax-rate reference:
 *        - taxRateId           = products.tax_rate_id (FK reference, nullable)
 *        - taxRateBasisPoints  = effective rate in basis-points via PricingRepository
 *      Resolved through PricingRepositoryInterface::getTaxRateBasisPointsForProduct().
 *      If not resolvable, a RuntimeException is thrown — NO silent fallback default.
 *   3. Snapshot the deposit reference:
 *        - pfandSetId       = gebinde.pfand_set_id (null when absent)
 *        - unitDepositMilli = recursive PfandSet sum via PfandCalculator
 *      When $isDepositExempt = true both deposit fields are zeroed/nulled.
 *
 * @see PriceResolverService       for the three-level price resolution cascade
 * @see PricingRepositoryInterface for the tax-rate join
 * @see PfandCalculator            for the recursive deposit-tree summation
 * @see OrderItemPricingSnapshot   for the returned value object
 */
class OrderPricingService
{
    public function __construct(
        private readonly PriceResolverService       $priceResolver,
        private readonly PricingRepositoryInterface $pricingRepo,
        private readonly PfandCalculator            $pfandCalculator,
    ) {}

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Build a complete pricing snapshot for one order-item line.
     *
     * @throws RuntimeException when the product's tax rate cannot be resolved.
     *         Order creation must be aborted — no silent default exists.
     */
    public function priceOrderItem(
        Product  $product,
        Customer $customer,
        bool     $isDepositExempt = false,
    ): OrderItemPricingSnapshot {
        $priceResult = $this->priceResolver->resolveForCustomer($product, $customer);

        $taxRateId = $this->resolveTaxRateId($product);
        $taxRateBp = $this->resolveTaxRateBasisPoints($product);

        [$pfandSetId, $unitDepositMilli] = $isDepositExempt
            ? [null, 0]
            : $this->resolvePfandSnapshot($product);

        // BUG-4: snapshot the deposit tax rate — equals the product's VAT rate
        // when a deposit applies (German law), 0 when deposit-exempt or no deposit.
        $depositTaxRateBp = ($unitDepositMilli > 0) ? $taxRateBp : 0;

        return new OrderItemPricingSnapshot(
            netMilli:                   $priceResult->netMilli,
            grossMilli:                 $priceResult->grossMilli,
            priceSource:                $priceResult->source,
            taxRateId:                  $taxRateId,
            taxRateBasisPoints:         $taxRateBp,
            pfandSetId:                 $pfandSetId,
            unitDepositMilli:           $unitDepositMilli,
            depositTaxRateBasisPoints:  $depositTaxRateBp,
        );
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Read the raw products.tax_rate_id FK value.
     * Returns null when zero/negative (Tax module inactive).
     */
    private function resolveTaxRateId(Product $product): ?int
    {
        $raw = $product->tax_rate_id;

        return (is_int($raw) && $raw > 0) ? $raw : null;
    }

    /**
     * Resolve effective tax-rate in basis-points via the repository join.
     *
     * @throws RuntimeException when the rate cannot be resolved or is <= 0.
     */
    private function resolveTaxRateBasisPoints(Product $product): int
    {
        $bp = $this->pricingRepo->getTaxRateBasisPointsForProduct($product->id);

        if ($bp <= 0) {
            throw new RuntimeException(
                sprintf(
                    'Tax rate basis points for product #%d resolved to %d — '
                    . 'a positive value is required. Check products.tax_rate_id '
                    . 'and the tax_rates table.',
                    $product->id,
                    $bp,
                )
            );
        }

        return $bp;
    }

    /**
     * Resolve deposit snapshot fields for a product.
     *
     * @return array{0: int|null, 1: int}  [$pfandSetId, $unitDepositMilli]
     */
    private function resolvePfandSnapshot(Product $product): array
    {
        $product->loadMissing('gebinde');
        $gebinde = $product->gebinde;

        if ($gebinde === null) {
            return [null, 0];
        }

        $pfandSetId = $gebinde->pfand_set_id ?? null;

        if ($pfandSetId === null) {
            return [null, 0];
        }

        $depositMilli = $this->pfandCalculator->totalForGebinde($gebinde);

        return [(int) $pfandSetId, $depositMilli];
    }
}
