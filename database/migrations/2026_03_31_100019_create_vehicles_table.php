<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fahrzeugverwaltung.
 * Eigene Verwaltung für betriebliche Fahrzeuge.
 * Vorbereitet für Einsatzhistorie (tour_id, km, Schäden).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Stammdaten
            $table->string('internal_name', 100);
            $table->string('plate_number', 20)->unique();
            $table->string('manufacturer', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('vehicle_type', 50)->nullable(); // LKW, Transporter, PKW, Anhänger
            $table->string('vin', 50)->nullable();
            $table->date('first_registration')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('active')->default(true);
            $table->string('location', 255)->nullable();
            $table->text('notes')->nullable();

            // Technische Daten / Zuladung
            $table->unsignedInteger('gross_vehicle_weight')->nullable(); // kg Gesamtgewicht
            $table->unsignedInteger('empty_weight')->nullable();         // kg Leergewicht
            $table->unsignedInteger('payload_weight')->nullable();       // kg Nutzlast
            $table->unsignedInteger('load_volume')->nullable();          // Liter Ladevolumen
            $table->unsignedInteger('max_vpe_without_hand_truck')->nullable();
            $table->unsignedInteger('max_vpe_with_hand_truck')->nullable();
            $table->unsignedInteger('load_length')->nullable();          // mm
            $table->unsignedInteger('load_width')->nullable();           // mm
            $table->unsignedInteger('load_height')->nullable();          // mm
            $table->unsignedSmallInteger('seats')->nullable();
            $table->boolean('trailer_hitch')->default(false);
            $table->unsignedInteger('max_trailer_load')->nullable();     // kg
            $table->boolean('cooling_unit')->default(false);
            $table->string('required_license_class', 10)->nullable();    // B, BE, C1, C

            // Fristen
            $table->date('tuev_due_date')->nullable();
            $table->date('inspection_due_date')->nullable();
            $table->date('oil_service_due_date')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->unsignedInteger('current_mileage')->nullable();

            // Sync-Quelle (z.B. Ninox ninox_fahrzeug)
            $table->string('sync_source', 50)->nullable();
            $table->string('sync_source_id', 100)->nullable();

            $table->timestamps();
            $table->index(['active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
