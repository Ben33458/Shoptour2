<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_prices', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('product_id');

            // Highest-priority price override.  When a valid row exists for the
            // customer + product combination, no group adjustment is applied —
            // this value is used directly as the final price.
            $table->integer('price_net_milli');
            $table->integer('price_gross_milli');

            // Optional validity window — null means "always valid"
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->unique(['customer_id', 'product_id', 'valid_from'], 'cp_customer_product_from_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_prices');
    }
};
