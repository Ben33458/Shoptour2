<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-11 fix: Add min_order_value_milli to regular_delivery_tours.
 *
 * Enables the "Mindestbestellwert" (minimum order value) feature per tour.
 * When set, the checkout shows a warning and blocks order submission if
 * the cart gross total falls below this threshold.
 *
 * Value is stored as milli-cents (bigInteger).
 *   0 = no minimum (always orderable on this tour)
 *   e.g. 25_000_000 = 25,00 € minimum order value
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regular_delivery_tours', function (Blueprint $table): void {
            // Minimum cart gross total (milli-cents) required to place an order
            // on this tour. 0 = no minimum. Shown as a warning in checkout.
            $table->bigInteger('min_order_value_milli')
                ->default(0)
                ->after('min_gebinde_qty');
        });
    }

    public function down(): void
    {
        Schema::table('regular_delivery_tours', function (Blueprint $table): void {
            $table->dropColumn('min_order_value_milli');
        });
    }
};
