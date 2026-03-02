<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-3: Cart items — each row is one product in a cart with quantity.
 *
 * unit_price_gross_milli and pfand_milli are snapshots taken at the time
 * the item was added. Prices are re-resolved live on every cart display,
 * but these snapshots can be used for analytics or fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cart_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_gross_milli')->default(0);
            $table->unsignedBigInteger('pfand_milli')->default(0);
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('carts')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
