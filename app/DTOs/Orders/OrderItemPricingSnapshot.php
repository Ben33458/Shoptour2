<?php

declare(strict_types=1);

namespace App\DTOs\Orders;

/**
 * Immutable value object that holds all snapshot fields for one order-item line.
 *
 * Produced by OrderPricingService::priceOrderItem() and consumed by OrderService
 * when persisting an OrderItem row.  Keeping these fields in a dedicated DTO:
 *
 *   • makes the snapshot contract explicit and type-safe
 *   • allows OrderPricingService to be unit-tested without writing to the DB
 *   • lets future code (invoice generator, returns processing) reuse the same
 *     resolution logic without going through OrderService
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Naming convention: field names mirror the order_items DBML column names
 * exactly (no *_snapshot suffix — the entire order_items row IS the snapshot).
 *
 * Monetary convention: all *_milli fields are milli-cents (int).
 *   1 EUR = 1_000_000 milli-cents
 *
 * Tax-rate fields:
 *   taxRateId          – FK reference to tax_rates.id frozen at order time;
 *                        null when the Tax module is inactive or the product
 *                        carries no tax_rate_id
 *   taxRateBasisPoints – effective rate in basis-points (e.g. 1_900 = 19 %,
 *                        700 = 7 %; scale: 10_000 = 100 %).  Always resolved
 *                        from the product's TaxRate record; a missing/
 *                        unresolvable rate is a hard error — OrderPricingService
 *                        throws RuntimeException.
 *
 * Deposit fields:
 *   pfandSetId                 – pfand_set_id from the product's Gebinde frozen at order
 *                                time; null when the product has no deposit obligation or
 *                                the customer is deposit-exempt
 *   unitDepositMilli           – total deposit per ordered unit (milli-cents); 0 when
 *                                deposit-exempt or PfandSet has no components
 *   depositTaxRateBasisPoints  – BUG-4: VAT rate applied to the deposit in basis-points;
 *                                equals taxRateBasisPoints (same rate in German law);
 *                                0 when deposit-exempt or no deposit applies
 *
 * Price fields (from PriceResolverService):
 *   netMilli          – resolved net unit price
 *   grossMilli        – net × (1 + taxRate), integer half-up rounded
 *   priceSource       – PriceResult::SOURCE_* constant
 * ─────────────────────────────────────────────────────────────────────────────
 */
final readonly class OrderItemPricingSnapshot
{
    /**
     * @param int      $netMilli          Net unit price (milli-cents)
     * @param int      $grossMilli        Gross unit price (milli-cents)
     * @param string   $priceSource       PriceResult::SOURCE_* constant
     * @param int|null $taxRateId         tax_rates.id frozen at order time; null = Tax module inactive
     * @param int      $taxRateBasisPoints Effective tax rate in basis-points (scale: 10_000 = 100 %; must be > 0)
     * @param int|null $pfandSetId                pfand_sets.id from Gebinde at order time; null = none
     * @param int      $unitDepositMilli          Deposit per unit (milli-cents); 0 = no deposit
     * @param int      $depositTaxRateBasisPoints BUG-4: VAT rate for deposit in bp; 0 = exempt / no deposit
     */
    public function __construct(
        public readonly int    $netMilli,
        public readonly int    $grossMilli,
        public readonly string $priceSource,
        public readonly ?int   $taxRateId,
        public readonly int    $taxRateBasisPoints,
        public readonly ?int   $pfandSetId,
        public readonly int    $unitDepositMilli,
        public readonly int    $depositTaxRateBasisPoints = 0,
    ) {}

    /**
     * Returns all fields as an associative array keyed exactly as the
     * order_items column names expected by OrderItem::create().
     *
     * @return array{
     *   unit_price_net_milli: int,
     *   unit_price_gross_milli: int,
     *   price_source: string,
     *   tax_rate_id: int|null,
     *   tax_rate_basis_points: int,
     *   pfand_set_id: int|null,
     *   unit_deposit_milli: int,
     *   deposit_tax_rate_basis_points: int,
     * }
     */
    public function toOrderItemArray(): array
    {
        return [
            'unit_price_net_milli'         => $this->netMilli,
            'unit_price_gross_milli'        => $this->grossMilli,
            'price_source'                  => $this->priceSource,
            'tax_rate_id'                   => $this->taxRateId,
            'tax_rate_basis_points'         => $this->taxRateBasisPoints,
            'pfand_set_id'                  => $this->pfandSetId,
            'unit_deposit_milli'            => $this->unitDepositMilli,
            'deposit_tax_rate_basis_points' => $this->depositTaxRateBasisPoints,
        ];
    }
}
