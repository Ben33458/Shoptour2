<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-16: Add cost_milli (unit purchase price snapshot) to invoice_items.
 *
 * cost_milli is the unit purchase price (from SupplierProduct.purchase_price_milli)
 * at the time the draft invoice is generated.
 * Null when no supplier price exists or for non-product line types.
 * Used to compute Deckungsbeitrag (margin) in the reporting module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', static function (Blueprint $table): void {
            // Nullable: only filled for product lines where a supplier price exists
            $table->bigInteger('cost_milli')->nullable()->after('line_total_gross_milli');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', static function (Blueprint $table): void {
            $table->dropColumn('cost_milli');
        });
    }
};
