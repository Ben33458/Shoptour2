<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positionen auf dem Rückgabeschein.
 *
 * Schadenstatus:
 *   - none: kein Schaden
 *   - damaged: defekt (nicht mehr verleihbar)
 *   - not_rentable: nicht verleihbar
 *   - damaged_but_still_rentable: defekt (Kleinigkeit), weiterhin verleihbar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_return_slip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_return_slip_id')
                ->constrained('rental_return_slips')
                ->cascadeOnDelete();
            $table->foreignId('rental_booking_item_id')
                ->constrained('rental_booking_items')
                ->restrictOnDelete();
            $table->unsignedInteger('returned_quantity');
            $table->enum('clean_status', ['clean', 'dirty'])->default('clean');
            $table->enum('damage_status', [
                'none',
                'damaged',
                'not_rentable',
                'damaged_but_still_rentable',
            ])->default('none');
            $table->unsignedBigInteger('damage_tariff_id')->nullable();
            $table->foreign('damage_tariff_id')
                ->references('id')
                ->on('damage_tariffs')
                ->nullOnDelete();
            // Suggested extra charge from tariff (milli-cents)
            $table->unsignedBigInteger('suggested_extra_charge_milli')->default(0);
            // Admin/driver can override
            $table->unsignedBigInteger('manual_extra_charge_milli')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo_path', 500)->nullable();
            $table->timestamps();
            $table->index('rental_return_slip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_return_slip_items');
    }
};
