<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MHD-Regeln nach Priorität: Artikel > Lager > Kategorie > Warengruppe > Default.
     *
     * bezug_typ = 'default' → bezug_id = null (globale Fallback-Regel)
     */
    public function up(): void
    {
        Schema::create('mhd_regeln', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->enum('bezug_typ', ['artikel', 'lager', 'kategorie', 'warengruppe', 'default']);
            $table->unsignedBigInteger('bezug_id')->nullable();

            $table->enum('modus', ['nie', 'optional', 'pflichtig'])->default('optional');

            // Warn-Schwellenwert in Tagen (0 = nicht konfiguriert)
            $table->unsignedSmallInteger('warnung_tage')->default(30);
            $table->unsignedSmallInteger('kritisch_tage')->default(14);

            $table->unsignedSmallInteger('prioritaet')->default(0);
            $table->boolean('aktiv')->default(true);

            $table->timestamps();

            $table->index(['bezug_typ', 'bezug_id']);
            $table->index('prioritaet');
        });

        // Globale Default-Regel einfügen
        DB::table('mhd_regeln')->insert([
            'bezug_typ'    => 'default',
            'bezug_id'     => null,
            'modus'        => 'optional',
            'warnung_tage' => 30,
            'kritisch_tage' => 14,
            'prioritaet'   => 0,
            'aktiv'        => true,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mhd_regeln');
    }
};
