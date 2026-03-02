<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');

            // Allowed: purchase_in | sale_out | correction |
            //          transfer_in | transfer_out | bundle_explosion
            $table->string('movement_type');

            // Positive = stock in, Negative = stock out
            $table->decimal('quantity_delta', 14, 3);

            // Optional back-reference to the triggering document
            $table->string('reference_type')->nullable();   // 'order', 'supplier_order', 'manual', …
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('note')->nullable();

            // Audit trail: who created this movement
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Append-only journal — no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->cascadeOnDelete();

            // Efficient per-product / per-warehouse queries
            $table->index(['product_id', 'warehouse_id']);

            // Look up all movements for an order / supplier order / …
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
