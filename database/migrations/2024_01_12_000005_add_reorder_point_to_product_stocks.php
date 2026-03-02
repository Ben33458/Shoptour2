<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds reorder_point to product_stocks.
 *
 * reorder_point: when available quantity (quantity - reserved_quantity)
 * falls below this threshold the replenishment suggestion command will
 * recommend a purchase order for this product/warehouse combination.
 *
 * Defaults to 0 (disabled) so existing rows are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stocks', function (Blueprint $table): void {
            $table->decimal('reorder_point', 14, 3)->default(0)->after('reserved_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('product_stocks', function (Blueprint $table): void {
            $table->dropColumn('reorder_point');
        });
    }
};
