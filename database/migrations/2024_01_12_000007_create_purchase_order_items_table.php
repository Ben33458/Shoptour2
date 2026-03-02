<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items on a purchase order.
 *
 * received_qty: filled in during goods receipt (kolabri:po:receive).
 * When null the item has not yet been received.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');

            $table->decimal('qty', 12, 3);

            // Purchase price per unit in milli-cents at the time of ordering
            $table->bigInteger('unit_purchase_milli')->default(0);

            // qty × unit_purchase_milli
            $table->bigInteger('line_total_milli')->default(0);

            // Filled on receipt; null = not yet received
            $table->decimal('received_qty', 12, 3)->nullable();

            $table->timestamps();

            $table->foreign('purchase_order_id')
                ->references('id')
                ->on('purchase_orders')
                ->cascadeOnDelete();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();

            $table->index('purchase_order_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
