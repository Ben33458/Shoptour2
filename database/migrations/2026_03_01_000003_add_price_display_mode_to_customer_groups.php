<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-2: Add price_display_mode ENUM to customer_groups table.
 *
 * Controls whether product prices are shown as net or gross
 * for customers belonging to this group.
 * Default: 'brutto' (gross display for B2C customers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->enum('price_display_mode', ['netto', 'brutto'])
                ->default('brutto')
                ->after('is_deposit_exempt');
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropColumn('price_display_mode');
        });
    }
};
