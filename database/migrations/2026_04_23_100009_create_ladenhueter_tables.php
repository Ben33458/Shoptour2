<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konfigurierbare Schwellenwerte (single-row, adminseitig)
        Schema::create('ladenhueter_regeln', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedSmallInteger('tage_ohne_verkauf')->default(90);
            $table->unsignedSmallInteger('max_lagerdauer_tage')->default(180);
            $table->unsignedSmallInteger('max_bestandsreichweite_tage')->default(180);
            $table->boolean('aktiv')->default(true);

            $table->timestamps();
        });

        // Aktionen und Status je Artikel (optional je Lager)
        Schema::create('ladenhueter_status', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');

            $table->unsignedBigInteger('warehouse_id')->nullable();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');

            $table->enum('status', ['beobachten', 'nachbestellung_blockiert', 'abverkauf_foerdern', 'preisaktion_pruefen', 'ignoriert'])->default('beobachten');
            $table->text('notiz')->nullable();

            $table->unsignedBigInteger('gesetzt_von')->nullable();
            $table->foreign('gesetzt_von')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('gesetzt_am')->nullable();

            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
            $table->index('status');
        });

        // Standard-Konfiguration einfügen
        DB::table('ladenhueter_regeln')->insert([
            'tage_ohne_verkauf'        => 90,
            'max_lagerdauer_tage'      => 180,
            'max_bestandsreichweite_tage' => 180,
            'aktiv'                    => true,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ladenhueter_status');
        Schema::dropIfExists('ladenhueter_regeln');
    }
};
