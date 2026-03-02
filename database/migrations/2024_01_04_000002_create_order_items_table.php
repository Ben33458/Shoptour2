<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id');

            // Snapshot of the product at order time.
            // The product_id is kept for reference but data may diverge if the
            // product is later updated or deleted.
            $table->unsignedBigInteger('product_id');

            // --- Price snapshot (milli-cents, int) ---
            // These values are frozen at order creation and never recomputed.
            $table->bigInteger('unit_price_net_milli');
            $table->bigInteger('unit_price_gross_milli');

            // Source of the resolved price (mirrors PriceResult::SOURCE_* constants):
            // "customer_price" | "group_price" | "base_plus_adjustment"
            $table->string('price_source');

            // Tax rate snapshot in basis points.
            // Stored directly on the order item — independent of any tax_rates table.
            // Default 190_000 = 19 % German standard VAT.
            // Document: this is a frozen snapshot; it does NOT follow tax_rate_id changes.
            $table->unsignedInteger('tax_rate_basis_points_snapshot')->default(190_000);

            // Deposit per unit (milli-cents). 0 when customer is deposit-exempt.
            $table->bigInteger('pfand_brutto_milli_per_unit')->default(0);

            // Ordered quantity
            $table->integer('qty');

            // True when this item exceeded available warehouse stock at order time.
            // The order still proceeds (backorder model).
            $table->boolean('is_backorder')->default(false);

            // Human-readable product name frozen at order time.
            // Allows historic display even if the product is renamed or deleted.
            $table->string('product_name_snapshot');

            // Frozen Artikelnummer at order time.
            $table->string('artikelnummer_snapshot');

            $table->timestamps();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->cascadeOnDelete();

            // product_id is intentionally NOT FK-constrained: products may be
            // soft-deleted or archived, and the snapshot must remain intact.
            $table->index('product_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
