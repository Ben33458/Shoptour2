<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Importläufe (eine ODS-Datei = ein Lauf)
        Schema::create('import_bestandsaufnahme_laeufe', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->string('dateiname', 255);
            $table->enum('status', ['verarbeitung', 'abgeschlossen', 'fehler'])->default('verarbeitung');
            $table->unsignedSmallInteger('anzahl_blaetter')->default(0);
            $table->unsignedInteger('anzahl_rohzeilen')->default(0);
            $table->unsignedInteger('anzahl_konflikte')->default(0);
            $table->text('fehler_log')->nullable();

            $table->unsignedBigInteger('importiert_von')->nullable();
            $table->foreign('importiert_von')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });

        // Mapping-Konfiguration je Tabellenblatt/Lieferant
        Schema::create('import_bestandsaufnahme_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->string('tabellenblatt', 100)->unique();
            $table->unsignedBigInteger('lieferant_id')->nullable();
            $table->foreign('lieferant_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->unsignedBigInteger('lager_id_standard')->nullable();
            $table->foreign('lager_id_standard')->references('id')->on('warehouses')->onDelete('set null');

            // Spaltenmapping (null = Spalte nicht vorhanden)
            $table->string('spalte_kolabri_artnr', 50)->nullable();
            $table->string('spalte_lieferanten_artnr', 50)->nullable();
            $table->string('spalte_produktname', 50)->nullable();
            $table->string('spalte_mindestbestand', 50)->nullable();
            $table->string('spalte_bestand', 50)->nullable();
            $table->string('spalte_bestellmenge', 50)->nullable();
            $table->string('spalte_mhd', 50)->nullable();
            $table->string('spalte_vpe_hinweis', 50)->nullable();
            $table->string('spalte_bestellhinweis', 50)->nullable();

            // Typ A/B/C aus Spezifikation
            $table->enum('blatt_typ', ['A', 'B', 'C', 'unbekannt'])->default('unbekannt');
            $table->text('notiz')->nullable();
            $table->boolean('aktiv')->default(true);

            $table->timestamps();
        });

        // Rohzeilen pro Importlauf
        Schema::create('import_bestandsaufnahme_rohzeilen', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('importlauf_id');
            $table->foreign('importlauf_id')
                ->references('id')->on('import_bestandsaufnahme_laeufe')->onDelete('cascade');

            $table->string('tabellenblatt', 100);
            $table->unsignedInteger('zeilennummer');
            $table->json('roh_payload_json');

            $table->enum('erkannt_status', [
                'neu',
                'gemappt',
                'konflikt',
                'uebernommen',
                'verworfen',
                'manuell_zugeordnet',
                'pruefbeduertig',
            ])->default('neu');

            $table->text('mapping_hinweis')->nullable();

            // Aufgelöste Referenzen (nach Mapping)
            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->unsignedBigInteger('lieferant_id')->nullable();
            $table->foreign('lieferant_id')->references('id')->on('suppliers')->onDelete('set null');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['importlauf_id', 'tabellenblatt'], 'imp_ba_rohzeilen_lauf_blatt_idx');
            $table->index(['erkannt_status']);
        });

        // Konflikte ODS vs. DB
        Schema::create('import_bestandsaufnahme_konflikte', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            $table->unsignedBigInteger('rohzeile_id');
            $table->foreign('rohzeile_id')
                ->references('id')->on('import_bestandsaufnahme_rohzeilen')->onDelete('cascade');

            $table->unsignedBigInteger('product_id')->nullable();
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');

            $table->enum('konflikt_typ', [
                'abweichender_mindestbestand',
                'abweichender_standard_lieferant',
                'unklare_verpackungseinheit',
                'produkt_ohne_match',
                'mehrere_moegliche_matches',
                'fehlende_kolabri_artnr',
                'widersprüchliche_lieferanten_artnr',
            ]);

            $table->string('feld', 100)->nullable();
            $table->text('ods_wert')->nullable();
            $table->text('db_wert')->nullable();
            $table->text('hinweis')->nullable();

            $table->enum('aktion', ['offen', 'uebernehmen', 'verwerfen', 'manuell', 'referenz'])->default('offen');
            $table->unsignedBigInteger('bearbeitet_von')->nullable();
            $table->timestamp('bearbeitet_am')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('aktion');
            $table->index('konflikt_typ');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_bestandsaufnahme_konflikte');
        Schema::dropIfExists('import_bestandsaufnahme_rohzeilen');
        Schema::dropIfExists('import_bestandsaufnahme_mappings');
        Schema::dropIfExists('import_bestandsaufnahme_laeufe');
    }
};
