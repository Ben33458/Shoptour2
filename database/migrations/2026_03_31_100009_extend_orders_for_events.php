<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend orders table with event-specific fields.
 *
 * Festbedarf darf NUR im Kontext eines Eventauftrags gebucht werden.
 * is_event_order = true markiert einen Auftrag als Eventauftrag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Event order flag
            $table->boolean('is_event_order')->default(false)->after('is_pos_sale');

            // Event location: FK or free-text address
            $table->unsignedBigInteger('event_location_id')->nullable()->after('is_event_order');
            $table->foreign('event_location_id')
                ->references('id')
                ->on('event_locations')
                ->nullOnDelete();
            $table->string('event_location_name', 255)->nullable();
            $table->string('event_location_street', 255)->nullable();
            $table->string('event_location_zip', 20)->nullable();
            $table->string('event_location_city', 100)->nullable();

            // On-site contact
            $table->string('event_contact_name', 100)->nullable();
            $table->string('event_contact_phone', 50)->nullable();

            // Logistics hints
            $table->text('event_access_notes')->nullable();
            $table->text('event_setup_notes')->nullable();
            $table->boolean('event_has_power')->default(false);
            $table->boolean('event_suitable_ground')->default(true);

            // Delivery time windows (Wunsch-Zeitfenster: min. 2 Stunden)
            $table->date('desired_delivery_date')->nullable();
            $table->time('desired_delivery_time_from')->nullable();
            $table->time('desired_delivery_time_to')->nullable();
            $table->date('desired_pickup_date')->nullable();
            $table->time('desired_pickup_time_from')->nullable();
            $table->time('desired_pickup_time_to')->nullable();

            // Confirmed time windows (set by admin)
            $table->time('confirmed_delivery_time_from')->nullable();
            $table->time('confirmed_delivery_time_to')->nullable();
            $table->time('confirmed_pickup_time_from')->nullable();
            $table->time('confirmed_pickup_time_to')->nullable();

            // Logistics classification (auto-calculated from rental items)
            $table->enum('logistics_class', ['small', 'normal', 'truck'])->nullable();

            // Delivery/pickup mode
            $table->enum('event_delivery_mode', ['delivery', 'self_pickup'])->default('delivery');
            $table->enum('event_pickup_mode', ['pickup_by_us', 'self_return'])->default('pickup_by_us');

            // Prepayment: 50% Vorkasse auf Miete, Getränke, Service, Lieferkosten (OHNE Pfand)
            $table->unsignedBigInteger('prepayment_required_milli')->default(0);
            $table->date('prepayment_due_date')->nullable(); // spätestens 7 Tage vor Event
            $table->boolean('prepayment_received')->default(false);

            // Distance from warehouse for delivery surcharge (Entfernungsaufschlag)
            $table->unsignedInteger('distance_km')->nullable();

            $table->index('is_event_order');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['event_location_id']);
            $table->dropIndex(['is_event_order']);
            $table->dropColumn([
                'is_event_order',
                'event_location_id',
                'event_location_name',
                'event_location_street',
                'event_location_zip',
                'event_location_city',
                'event_contact_name',
                'event_contact_phone',
                'event_access_notes',
                'event_setup_notes',
                'event_has_power',
                'event_suitable_ground',
                'desired_delivery_date',
                'desired_delivery_time_from',
                'desired_delivery_time_to',
                'desired_pickup_date',
                'desired_pickup_time_from',
                'desired_pickup_time_to',
                'confirmed_delivery_time_from',
                'confirmed_delivery_time_to',
                'confirmed_pickup_time_from',
                'confirmed_pickup_time_to',
                'logistics_class',
                'event_delivery_mode',
                'event_pickup_mode',
                'prepayment_required_milli',
                'prepayment_due_date',
                'prepayment_received',
                'distance_km',
            ]);
        });
    }
};
