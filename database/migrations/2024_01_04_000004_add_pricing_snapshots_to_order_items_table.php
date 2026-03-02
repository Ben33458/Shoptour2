<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds two additional snapshot columns to order_items:
 *
 *   tax_rate_id_snapshot
 *       The raw value of products.tax_rate_id at order-creation time.
 *       Stored as a plain integer, no FK enforcement (Tax module is out of scope).
 *       Allows auditors to trace which tax-rate record was active when the order
 *       was placed, without requiring the tax_rates table to still exist.
 *       0 = unknown / Tax module inactive.
 *
 *   pfand_set_id_snapshot
 *       The pfand_set_id from the product's Gebinde at order-creation time.
 *       Nullable: null when the product has no Gebinde or the Gebinde has no
 *       PfandSet (e.g. products with no deposit obligation or deposit-exempt customers).
 *       Stored without FK constraint — PfandSets may be archived in the future.
 *
 * These two columns complement the already-existing snapshot fields:
 *   - unit_price_net_milli / unit_price_gross_milli  (price)
 *   - price_source                                    (resolution path)
 *   - tax_rate_basis_points_snapshot                  (effective rate in bp)
 *   - pfand_brutto_milli_per_unit                     (deposit total in milli-cents)
 *
 * Together they give a complete, self-contained audit trail for every line item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            // Raw products.tax_rate_id value frozen at order time.
            // Not a FK — Tax module is out of scope.
            // 0 means "unknown / not set".
            $table->unsignedInteger('tax_rate_id_snapshot')->default(0)->after('tax_rate_basis_points_snapshot');

            // pfand_set_id from the product's Gebinde at order time.
            // NULL when no Gebinde / no PfandSet (or deposit-exempt customer).
            $table->unsignedBigInteger('pfand_set_id_snapshot')->nullable()->after('tax_rate_id_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['tax_rate_id_snapshot', 'pfand_set_id_snapshot']);
        });
    }
};
