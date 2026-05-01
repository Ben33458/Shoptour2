<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Import-Tracking ────────────────────────────────────────────────
        Schema::create('primeur_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('source')->comment('IT_Drink oder C_IT_Drink');
            $table->string('phase')->comment('customers|articles|orders|cash_receipts|cash_daily|cash_sessions');
            $table->string('status')->default('pending')->comment('pending|running|completed|failed');
            $table->unsignedInteger('records_imported')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('primeur_source_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')->constrained('primeur_import_runs')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('source_type')->comment('main_data|main_orders|daily_cash|annual_summary|annual_kasse');
            $table->date('data_date')->nullable()->comment('Dateidatum bei Tages-/Monatsdateien');
            $table->unsignedInteger('records_imported')->default(0);
            $table->timestamps();
        });

        // ── Stammdaten ─────────────────────────────────────────────────────
        Schema::create('primeur_customers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('primeur_id')->unique()->comment('RecordID aus Tb_Adressen');
            $table->string('suchname', 25)->nullable();
            $table->string('name1', 40)->nullable();
            $table->string('name2', 25)->nullable();
            $table->string('name3', 40)->nullable();
            $table->string('strasse', 40)->nullable();
            $table->string('hausnr', 10)->nullable();
            $table->string('plz', 8)->nullable();
            $table->string('ort', 40)->nullable();
            $table->string('vorwahl', 10)->nullable();
            $table->string('telefon', 30)->nullable();
            $table->string('telefon2', 30)->nullable();
            $table->string('fax', 30)->nullable();
            $table->string('email', 80)->nullable();
            $table->string('kundennummer', 10)->nullable()->index();
            $table->string('kundennummer2', 10)->nullable();
            $table->string('kundengruppe', 30)->nullable();
            $table->string('preisgruppe', 30)->nullable();
            $table->string('zahlungsart', 30)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamp('anleg_time')->nullable();
            $table->timestamp('update_time')->nullable();
            $table->timestamps();
        });

        Schema::create('primeur_articles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('primeur_id')->unique()->comment('RecordID aus Tb_Artikel');
            $table->string('artikelnummer', 10)->nullable()->index();
            $table->string('kurzbezeichnung', 20)->nullable();
            $table->string('bezeichnung', 40)->nullable();
            $table->string('zusatz', 15)->nullable();
            $table->string('warengruppe', 30)->nullable();
            $table->string('warenuntergruppe', 30)->nullable();
            $table->string('artikelgruppe', 30)->nullable();
            $table->decimal('inhalt', 10, 3)->nullable();
            $table->string('masseinheit', 10)->nullable();
            $table->string('vk_bezug', 10)->nullable();
            $table->string('hersteller', 40)->nullable();
            $table->boolean('aktiv')->default(true);
            $table->timestamp('anleg_time')->nullable();
            $table->timestamp('update_time')->nullable();
            $table->timestamps();
        });

        // ── Aufträge / Lieferscheine / Rechnungen ──────────────────────────
        Schema::create('primeur_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('primeur_id')->unique()->comment('RecordID aus Tb_AuftragHaupt');
            $table->unsignedBigInteger('kunden_id')->nullable()->index()->comment('Referenz auf primeur_customers.primeur_id');
            $table->unsignedBigInteger('beleg_nr')->nullable()->index();
            $table->string('auftragsart', 30)->nullable()->index()->comment('Rechnung|Lieferschein|Gutschrift|...');
            $table->string('rechnungsart', 30)->nullable();
            $table->date('belegdatum')->nullable()->index();
            $table->date('lieferdatum')->nullable();
            $table->date('rechnungsdatum')->nullable();
            $table->string('tour', 30)->nullable();
            $table->string('sachbearbeiter', 30)->nullable();
            $table->string('status', 20)->nullable();
            $table->boolean('storno')->default(false)->index();
            $table->string('zahlungsart', 30)->nullable();
            $table->decimal('warenwert_gesamt', 12, 4)->nullable();
            $table->decimal('gesamt_netto', 12, 4)->nullable();
            $table->decimal('mehrwertsteuer', 12, 4)->nullable();
            $table->decimal('endbetrag', 12, 4)->nullable();
            $table->decimal('skonto', 10, 4)->nullable();
            $table->string('waehrung', 5)->default('EUR');
            $table->timestamp('anleg_time')->nullable();
            $table->timestamps();

            $table->index(['belegdatum', 'auftragsart']);
            $table->index(['kunden_id', 'belegdatum']);
        });

        Schema::create('primeur_order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('primeur_id')->unique()->comment('RecordID aus Tb_AuftragArtikel');
            $table->unsignedBigInteger('order_id')->index()->comment('HauptSatzID → primeur_orders.primeur_id');
            $table->unsignedBigInteger('kunden_id')->nullable();
            $table->unsignedBigInteger('artikel_id')->nullable()->index();
            $table->string('artikelnummer', 10)->nullable();
            $table->string('artikeleinheit', 1)->nullable();
            $table->string('artikel_bezeichnung', 60)->nullable();
            $table->decimal('bestellmenge', 10, 3)->nullable();
            $table->decimal('liefermenge', 10, 3)->nullable();
            $table->decimal('fehlmenge', 10, 3)->nullable();
            $table->decimal('vk_preis_regulaer', 10, 4)->nullable();
            $table->decimal('vk_preis_tatsaechlich', 10, 4)->nullable();
            $table->decimal('vk_preis_aktion', 10, 4)->nullable();
            $table->decimal('listen_ek', 10, 4)->nullable();
            $table->decimal('effektiver_ek', 10, 4)->nullable();
            $table->decimal('pfandbetrag', 10, 4)->nullable();
            $table->boolean('storno')->default(false);
            $table->timestamps();

            $table->index(['order_id', 'artikel_id']);
        });

        // ── Kassenbelege (täglich, aus Jahresordnern) ──────────────────────
        Schema::create('primeur_cash_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('source_file', 30)->nullable()->comment('Dateiname der Quelldatei, z.B. 01210102');
            $table->unsignedInteger('source_record_id')->nullable()->comment('RecordID aus Tb_BelegHaupt');
            $table->date('datum')->nullable()->index();
            $table->unsignedBigInteger('belegnummer')->nullable()->index();
            $table->unsignedInteger('sitzungs_id')->nullable();
            $table->unsignedInteger('kassen_nr')->nullable();
            $table->unsignedBigInteger('kunden_id')->nullable();
            $table->string('preisgruppe', 30)->nullable();
            $table->unsignedSmallInteger('belegstatus')->nullable()->comment('1=Normal, 0=Storno');
            $table->string('belegtext', 40)->nullable();
            $table->unsignedSmallInteger('kartenart')->nullable();
            $table->boolean('ist_storno')->default(false)->index();
            $table->decimal('gesamtbetrag', 10, 4)->nullable();
            $table->decimal('pfandeinnahmen', 10, 4)->nullable();
            $table->decimal('pfandausgaben', 10, 4)->nullable();
            $table->decimal('bar_gegeben', 10, 4)->nullable();
            $table->decimal('scheckbetrag', 10, 4)->nullable();
            $table->decimal('gesamtertrag', 10, 4)->nullable();
            $table->decimal('belegrabatt', 10, 4)->nullable();
            $table->decimal('kartenzahlung', 10, 4)->nullable();
            $table->decimal('barbetrag', 10, 4)->nullable();
            $table->decimal('mwst_betrag_1', 10, 4)->nullable();
            $table->decimal('mwst_betrag_2', 10, 4)->nullable();
            $table->decimal('mwst_satz_1', 5, 2)->nullable();
            $table->decimal('mwst_satz_2', 5, 2)->nullable();
            $table->timestamps();

            $table->index(['datum', 'ist_storno']);
            $table->index(['datum', 'kassen_nr']);
            $table->unique(['source_file', 'source_record_id'], 'unique_source_beleg');
        });

        Schema::create('primeur_cash_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cash_receipt_id')->constrained('primeur_cash_receipts')->cascadeOnDelete();
            $table->unsignedInteger('source_record_id')->nullable();
            $table->date('datum')->nullable()->index();
            $table->unsignedBigInteger('belegnummer')->nullable();
            $table->unsignedBigInteger('artikel_id')->nullable()->index();
            $table->string('artikeleinheit', 1)->nullable();
            $table->string('artikel_bezeichnung', 40)->nullable();
            $table->decimal('menge', 10, 3)->nullable();
            $table->decimal('vk_preis_regulaer', 10, 4)->nullable();
            $table->decimal('vk_preis_tatsaechlich', 10, 4)->nullable();
            $table->decimal('vk_preis', 10, 4)->nullable();
            $table->decimal('vk_preis_aktion', 10, 4)->nullable();
            $table->decimal('vk_preis_rabatt', 10, 4)->nullable();
            $table->decimal('pfandbetrag', 10, 4)->nullable();
            $table->decimal('mwst_satz', 5, 2)->nullable();
            $table->boolean('sonderverkauf')->default(false);
            $table->boolean('zugabe')->default(false);
            $table->boolean('aktion')->default(false);
            $table->timestamps();

            $table->index(['datum', 'artikel_id']);
        });

        // ── Tagesumsatz-Zusammenfassung (aus Tb_U*.mdb) ───────────────────
        Schema::create('primeur_cash_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('datum')->unique()->index();
            $table->unsignedSmallInteger('markt_id')->nullable();
            $table->decimal('bankeinreichung', 12, 4)->nullable();
            $table->decimal('storno_ware', 10, 4)->default(0);
            $table->decimal('storno_pfand', 10, 4)->default(0);
            $table->decimal('wechselgeld', 10, 4)->default(0);
            $table->decimal('bezahlt_bar', 12, 4)->nullable();
            $table->decimal('bezahlt_scheck', 10, 4)->default(0);
            $table->decimal('warenwert_gesamt', 12, 4)->nullable()->comment('Warenwert1+2+3');
            $table->decimal('pfand_einnahmen', 10, 4)->default(0);
            $table->decimal('pfand_ausgaben', 10, 4)->default(0);
            $table->unsignedSmallInteger('anz_abschoepf_bar')->default(0);
            $table->decimal('abschoepf_bar', 10, 4)->default(0);
            $table->unsignedSmallInteger('anz_ein_aus_zahlungen_bar')->default(0);
            $table->decimal('ein_aus_zahlungen_bar', 10, 4)->default(0);
            $table->decimal('ertrag', 12, 4)->nullable();
            $table->unsignedSmallInteger('anz_rabatt')->default(0);
            $table->decimal('rabattbetrag', 10, 4)->default(0);
            $table->unsignedSmallInteger('anz_karte')->default(0);
            $table->decimal('kartenbetrag', 12, 4)->default(0);
            $table->decimal('barbetrag', 12, 4)->nullable();
            $table->unsignedSmallInteger('anz_belege')->default(0);
            $table->decimal('belegbetrag', 12, 4)->nullable();
            $table->decimal('storno_belege', 10, 4)->default(0);
            $table->decimal('storno_karte', 10, 4)->default(0);
            $table->decimal('storno_scheck', 10, 4)->default(0);
            $table->boolean('uebername_in_fibu')->default(false);
            $table->timestamps();

            $table->index(['datum']);
        });

        // ── Kassensitzungen (aus Tb_Kas*.mdb) ─────────────────────────────
        Schema::create('primeur_cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_record_id')->nullable();
            $table->string('source_file', 20)->nullable();
            $table->date('datum')->nullable()->index();
            $table->unsignedSmallInteger('kassen_nr')->nullable();
            $table->timestamp('session_start')->nullable();
            $table->timestamp('session_end')->nullable();
            $table->string('benutzer', 30)->nullable();
            $table->decimal('anfangsbestand', 10, 4)->nullable();
            $table->decimal('endbestand', 10, 4)->nullable();
            $table->decimal('kassenbestand', 10, 4)->nullable();
            $table->decimal('belegbetrag', 10, 4)->nullable();
            $table->unsignedSmallInteger('anzahl_belege')->default(0);
            $table->timestamps();

            $table->unique(['source_file', 'source_record_id'], 'unique_source_session');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('primeur_cash_sessions');
        Schema::dropIfExists('primeur_cash_daily');
        Schema::dropIfExists('primeur_cash_receipt_items');
        Schema::dropIfExists('primeur_cash_receipts');
        Schema::dropIfExists('primeur_order_items');
        Schema::dropIfExists('primeur_orders');
        Schema::dropIfExists('primeur_articles');
        Schema::dropIfExists('primeur_customers');
        Schema::dropIfExists('primeur_source_files');
        Schema::dropIfExists('primeur_import_runs');
    }
};
