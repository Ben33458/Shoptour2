<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add delivery address and delivery note to customers.
 *
 * A full Address entity is deferred to a future module; for now a single
 * free-form text column covers all Driver PWA display needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Free-form delivery address shown to the driver on each stop.
            // Example: "Hauptstraße 12, 64285 Darmstadt"
            $table->string('delivery_address_text', 512)->nullable()->after('email');

            // Per-customer standing delivery note shown to the driver.
            // Example: "Bitte klingeln", "Hinterhof linke Tür"
            $table->text('delivery_note')->nullable()->after('delivery_address_text');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['delivery_address_text', 'delivery_note']);
        });
    }
};
