<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The customer who placed the order
            $table->unsignedBigInteger('customer_id');

            // The customer group at the time of ordering (snapshot — group may change later)
            $table->unsignedBigInteger('customer_group_id_snapshot');

            // Lifecycle status
            // "pending"    – created, awaiting processing
            // "confirmed"  – acknowledged by staff
            // "shipped"    – dispatched to customer
            // "delivered"  – received by customer
            // "cancelled"  – voided
            $table->string('status')->default('pending');

            // Optional requested delivery date
            $table->date('delivery_date')->nullable();

            // Warehouse from which stock is drawn (nullable = not yet assigned)
            $table->unsignedBigInteger('warehouse_id')->nullable();

            // True when at least one item exceeded available stock at order time
            $table->boolean('has_backorder')->default(false);

            // Order-level monetary totals (milli-cents, all int)
            // Sum of (order_items.unit_price_net_milli  × qty)
            $table->bigInteger('total_net_milli')->default(0);
            // Sum of (order_items.unit_price_gross_milli × qty)
            $table->bigInteger('total_gross_milli')->default(0);
            // Total deposit obligation (milli-cents); 0 when customer is deposit-exempt
            $table->bigInteger('total_pfand_brutto_milli')->default(0);

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign('customer_group_id_snapshot')
                ->references('id')
                ->on('customer_groups')
                ->restrictOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->nullOnDelete();

            // Common query pattern: all orders for a customer, sorted by date
            $table->index(['customer_id', 'created_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
