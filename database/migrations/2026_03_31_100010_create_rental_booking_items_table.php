<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mietpositionen innerhalb eines Eventauftrags.
 * Reservierungen blockieren sofort (status = reserved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_booking_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('rental_item_id')->constrained('rental_items')->restrictOnDelete();
            $table->unsignedBigInteger('packaging_unit_id')->nullable();
            $table->foreign('packaging_unit_id')
                ->references('id')
                ->on('rental_packaging_units')
                ->nullOnDelete();
            $table->foreignId('rental_time_model_id')->constrained('rental_time_models')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('pieces_per_pack')->nullable(); // For packaging_based
            $table->unsignedInteger('total_pieces')->nullable();     // Derived: quantity * pieces_per_pack
            // Price snapshot in milli-cents
            $table->unsignedBigInteger('unit_price_net_milli');
            $table->unsignedBigInteger('total_price_net_milli');
            // Preferred or fixed unit for unit_based items
            $table->unsignedBigInteger('desired_specific_inventory_unit_id')->nullable();
            $table->foreign('desired_specific_inventory_unit_id')
                ->references('id')
                ->on('rental_inventory_units')
                ->nullOnDelete();
            $table->unsignedBigInteger('fixed_inventory_unit_id')->nullable();
            $table->foreign('fixed_inventory_unit_id')
                ->references('id')
                ->on('rental_inventory_units')
                ->nullOnDelete();
            // Status: ungeprüfte Reservierungen blockieren sofort
            $table->enum('status', [
                'reserved',
                'unreviewed',
                'confirmed',
                'rejected',
                'cancelled',
                'expired',
                'delivered',
                'returned',
            ])->default('unreviewed')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['rental_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_booking_items');
    }
};
