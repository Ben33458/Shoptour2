<?php

declare(strict_types=1);

namespace App\DTOs\Pricing;

/**
 * Immutable value object returned by PriceResolverService.
 *
 * All monetary amounts are stored as milli-cents (int).
 * Divide by 1_000_000 to obtain EUR; by 100_000 to obtain cents.
 *
 * Source values:
 *   "customer_price"       – individual price from customer_prices table
 *   "group_price"          – explicit price from customer_group_prices table
 *   "base_plus_adjustment" – base_price with customer group adjustment applied
 */
final readonly class PriceResult
{
    /**
     * @param int         $netMilli          Final net price in milli-cents
     * @param int         $grossMilli        Final gross price in milli-cents
     * @param string      $source            "customer_price"|"group_price"|"base_plus_adjustment"
     * @param bool        $isGuest           True when resolved via the default guest group
     * @param int         $customerGroupId   The CustomerGroup ID used for resolution
     */
    public function __construct(
        public readonly int    $netMilli,
        public readonly int    $grossMilli,
        public readonly string $source,
        public readonly bool   $isGuest,
        public readonly int    $customerGroupId,
    ) {}

    // -------------------------------------------------------------------------
    // Source constants
    // -------------------------------------------------------------------------

    public const SOURCE_CUSTOMER_PRICE       = 'customer_price';
    public const SOURCE_GROUP_PRICE          = 'group_price';
    public const SOURCE_BASE_PLUS_ADJUSTMENT = 'base_plus_adjustment';

    // -------------------------------------------------------------------------
    // Convenience accessors
    // -------------------------------------------------------------------------

    /**
     * Returns net amount in EUR as a float (for display only — never use for calculations).
     */
    public function netEur(): float
    {
        return $this->netMilli / 1_000_000;
    }

    /**
     * Returns gross amount in EUR as a float (for display only — never use for calculations).
     */
    public function grossEur(): float
    {
        return $this->grossMilli / 1_000_000;
    }

    /**
     * Serialize to plain array (useful for JSON responses or logging).
     *
     * @return array{net_milli: int, gross_milli: int, source: string, is_guest: bool, customer_group_id: int}
     */
    public function toArray(): array
    {
        return [
            'net_milli'          => $this->netMilli,
            'gross_milli'        => $this->grossMilli,
            'source'             => $this->source,
            'is_guest'           => $this->isGuest,
            'customer_group_id'  => $this->customerGroupId,
        ];
    }
}
