<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');

            // quantity MAY be negative (e.g. oversold stock)
            $table->decimal('quantity', 14, 3);

            // reserved_quantity >= 0: quantity committed to open orders
            $table->decimal('reserved_quantity', 14, 3)->default(0);

            // Only updated_at — rows are upserted, not versioned
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign('warehouse_id')
                ->references('id')
                ->on('warehouses')
                ->cascadeOnDelete();

            // One snapshot row per product/warehouse combination
            $table->unique(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
