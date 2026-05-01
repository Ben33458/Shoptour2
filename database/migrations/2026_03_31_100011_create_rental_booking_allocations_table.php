<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zuordnung von Buchungspositionen auf konkrete Inventareinheiten.
 * Für unit_based items: welche konkrete Einheit ist für welchen Zeitraum reserviert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_booking_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_booking_item_id')
                ->constrained('rental_booking_items')
                ->cascadeOnDelete();
            $table->foreignId('rental_inventory_unit_id')
                ->constrained('rental_inventory_units')
                ->restrictOnDelete();
            $table->dateTime('allocated_from');
            $table->dateTime('allocated_until');
            $table->enum('status', ['reserved', 'confirmed', 'delivered', 'returned', 'cancelled'])
                ->default('reserved')
                ->index();
            $table->timestamps();
            // Index for overlap checks (availability queries)
            $table->index(['rental_inventory_unit_id', 'allocated_from', 'allocated_until'], 'rba_unit_period_idx');
            $table->index(['rental_inventory_unit_id', 'status'], 'rba_unit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_booking_allocations');
    }
};
