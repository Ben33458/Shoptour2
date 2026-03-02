<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds immediate_payment flag and pos_sale flag to orders.
 *
 * immediate_payment: true for POS sales where payment is collected at the
 *                    time of sale (cash / card at the counter).
 * is_pos_sale:       marks orders that originated from the POS endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('immediate_payment')->default(false)->after('has_backorder');
            $table->boolean('is_pos_sale')->default(false)->after('immediate_payment');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['immediate_payment', 'is_pos_sale']);
        });
    }
};
