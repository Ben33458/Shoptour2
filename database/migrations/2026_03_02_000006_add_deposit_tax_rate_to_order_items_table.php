<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-4 fix: Add deposit_tax_rate_basis_points to order_items.
 *
 * This snapshot field records the VAT rate that applies to the deposit (Pfand)
 * component of the order line item, frozen at order-creation time.
 *
 * In German law the deposit tax rate equals the product's VAT rate (same rate
 * for the main article and its deposit). Storing it separately as a snapshot
 * column makes invoicing and accounting self-contained: the correct deposit
 * tax rate is readable directly from the order_items row without joining
 * back to tax_rates or products.
 *
 * Column definition:
 *   deposit_tax_rate_basis_points  unsignedInteger  NOT NULL  DEFAULT 0
 *
 * Default 0 is intentional for backward-compatibility with rows written before
 * this migration.  0 means "no deposit tax rate recorded" (e.g. deposit-exempt
 * customers or legacy rows).  New rows will always receive the correct non-zero
 * value from OrderPricingService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            // Deposit tax rate in basis-points, frozen at order time.
            // Scale: 10_000 = 100 % (1_900 = 19 %, 700 = 7 %).
            // Equals the product's tax_rate_basis_points (same VAT rate applies
            // to both the article price and its deposit in German law).
            // 0 = deposit-exempt customer, no deposit, or legacy row.
            $table->unsignedInteger('deposit_tax_rate_basis_points')
                ->default(0)
                ->after('unit_deposit_milli');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('deposit_tax_rate_basis_points');
        });
    }
};
