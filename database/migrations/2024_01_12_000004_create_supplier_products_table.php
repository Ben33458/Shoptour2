<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a supplier to a product they can supply, with ordering parameters.
 *
 * supplier_sku        – Supplier's own article number
 * min_order_qty       – Minimum order quantity (in our product's sales unit)
 * pack_size           – How many units per supplier pack; suggested_qty is
 *                       rounded up to the nearest multiple of pack_size
 * lead_time_days      – Typical lead time in calendar days
 * purchase_price_milli – Latest known purchase price (milli-cents / unit)
 * active              – When false, this supplier is not suggested for replenishment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_products', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('product_id');

            $table->string('supplier_sku', 128)->nullable();

            // Ordering constraints
            $table->decimal('min_order_qty', 10, 3)->default(1);
            $table->decimal('pack_size', 10, 3)->default(1);
            $table->unsignedSmallInteger('lead_time_days')->default(3);

            // Latest known purchase price (milli-cents per unit)
            $table->bigInteger('purchase_price_milli')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            // Only one active row per (supplier, product) pair
            $table->unique(['supplier_id', 'product_id'], 'sp_supplier_product_unique');

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_products');
    }
};
