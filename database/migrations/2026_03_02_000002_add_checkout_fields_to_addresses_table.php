<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4: Add drop-off location fields to addresses table.
 *
 * - drop_off_location: where to leave packages (keller, einfahrt, eg, garage, og1, sonstiges)
 * - drop_off_location_custom: free text when drop_off_location = sonstiges
 * - leave_at_door: whether the delivery can be left unattended
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->enum('drop_off_location', ['keller', 'einfahrt', 'eg', 'garage', 'og1', 'sonstiges'])
                ->nullable()
                ->after('phone');

            $table->text('drop_off_location_custom')->nullable()->after('drop_off_location');

            $table->boolean('leave_at_door')->default(false)->after('drop_off_location_custom');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn(['drop_off_location', 'drop_off_location_custom', 'leave_at_door']);
        });
    }
};
