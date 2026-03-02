<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-15: Add base-item fields to products.
 *
 *  is_base_item            – marks a product as the "base item" that holds LMIV data
 *  base_item_product_id    – nullable self-referential FK; EAN/variant products
 *                            point to their base item
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            // After the existing 'active' column (adjust position as needed)
            $table->boolean('is_base_item')->default(false)->after('active');
            $table->unsignedBigInteger('base_item_product_id')->nullable()->after('is_base_item');

            $table->foreign('base_item_product_id')
                  ->references('id')
                  ->on('products')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            $table->dropForeign(['base_item_product_id']);
            $table->dropColumn(['is_base_item', 'base_item_product_id']);
        });
    }
};
