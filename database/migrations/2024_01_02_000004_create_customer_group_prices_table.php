<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_group_prices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('customer_group_id');
            $table->unsignedBigInteger('product_id');

            // Explicit override price for this group+product combination.
            // When valid, this REPLACES base_price + adjustment entirely.
            // No further group adjustment is applied (price is considered final).
            $table->integer('price_net_milli');
            $table->integer('price_gross_milli');

            // Optional validity window — null means "always valid"
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            $table->timestamps();

            $table->foreign('customer_group_id')
                ->references('id')
                ->on('customer_groups')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            // A group can have only one active price row per product;
            // overlapping validity windows are enforced at the application layer.
            $table->unique(['customer_group_id', 'product_id', 'valid_from'], 'cgp_group_product_from_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_group_prices');
    }
};
