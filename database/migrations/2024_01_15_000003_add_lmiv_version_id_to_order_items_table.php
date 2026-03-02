<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-15: Snapshot the LMIV version on order items.
 *
 * At the moment an order item is created the currently active LMIV version
 * of the product (if any) is recorded here.  This gives a permanent,
 * immutable record of which LMIV label was legally in effect when the
 * product was sold.
 *
 * The column is nullable because:
 *  - old order items pre-date this feature
 *  - products without a base-item / without LMIV data have no version to link
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->unsignedBigInteger('lmiv_version_id')
                  ->nullable()
                  ->after('unit_deposit_milli');   // after the last existing snapshot column

            $table->foreign('lmiv_version_id')
                  ->references('id')
                  ->on('product_lmiv_versions')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', static function (Blueprint $table): void {
            $table->dropForeign(['lmiv_version_id']);
            $table->dropColumn('lmiv_version_id');
        });
    }
};
