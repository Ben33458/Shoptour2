<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ninox_veranstaltung', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('name')->nullable();
            $table->text('beschreibung')->nullable();
            $table->json('veranstaltungsjahr')->nullable();
            $table->json('kontakte')->nullable();
            $table->boolean('kunde_von_uns')->nullable();
            $table->json('dokumente')->nullable();
            $table->bigInteger('kunden')->nullable();
        });

        Schema::create('ninox_veranstaltungstage', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('datum')->nullable();
            $table->text('von')->nullable();
            $table->text('bis')->nullable();
            $table->bigInteger('veranstaltungsjahr')->nullable();
            $table->text('beschreibung')->nullable();
        });

        Schema::create('ninox_veranstaltungsjahr', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('jahr')->nullable();
            $table->text('titel')->nullable();
            $table->text('beschreibung')->nullable();
            $table->bigInteger('veranstaltung')->nullable();
            $table->json('veranstaltungstage')->nullable();
            $table->json('aufgaben')->nullable();
            $table->json('anlieferungen_abholungen')->nullable();
            $table->text('status')->nullable();
            $table->text('promt_fuer_kalkulation')->nullable();
            $table->text('getraenkeversorgung')->nullable();
            $table->bigInteger('anzahl_erwartete_personen')->nullable();
            $table->json('festbedarf_warenkorb')->nullable();
            $table->bigInteger('dauer_in_stunden')->nullable();
            $table->text('was_wird_angeboten')->nullable();
            $table->text('getraenke_groesse')->nullable();
            $table->text('durch_anderen_anbieter_angeboten')->nullable();
            $table->text('art_der_veranstaltung')->nullable();
            $table->text('getraenke_groesse2')->nullable();
            $table->text('kalkulierter_verbrauch')->nullable();
        });

        Schema::create('ninox_aufgaben', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('beschreibung')->nullable();
            $table->text('datum')->nullable();
            $table->text('uhrzeit')->nullable();
            $table->bigInteger('zeitdauer')->nullable();
            $table->bigInteger('veranstaltungsjahr')->nullable();
            $table->boolean('erledigt')->nullable();
            $table->text('datum_uhrzeit')->nullable();
        });

        Schema::create('ninox_kontakte', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('vorname')->nullable();
            $table->text('nachname')->nullable();
            $table->text('telefon')->nullable();
            $table->bigInteger('veranstaltung')->nullable();
            $table->bigInteger('lieferanten')->nullable();
            $table->text('e_mail')->nullable();
            $table->text('information')->nullable();
            $table->text('visitenkarte')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('anrede')->nullable();
            $table->text('status')->nullable();
            $table->text('rollen')->nullable();
            $table->text('spitzname')->nullable();
        });

        Schema::create('ninox_dokumente', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('dokument')->nullable();
            $table->bigInteger('veranstaltung')->nullable();
        });

        Schema::create('ninox_77_regelmaessige_aufgaben', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('name')->nullable();
            $table->json('done_history')->nullable();
            $table->text('ab_wann_wiederholen')->nullable();
            $table->text('naechste_faelligkeit')->nullable();
            $table->text('letzte_ausfuehrung')->nullable();
            $table->text('beschreibung')->nullable();
            $table->bigInteger('alle_x_monate')->nullable();
            $table->bigInteger('prioritaet')->nullable();
            $table->text('kategorie')->nullable();
            $table->text('zustaendigkeit')->nullable();
            $table->bigInteger('alle_x_tage')->nullable();
            $table->bigInteger('alle_x_wochen')->nullable();
            $table->bigInteger('alle_x_quartale')->nullable();
            $table->bigInteger('alle_x_jahre')->nullable();
        });

        Schema::create('ninox_done_history', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->text('datum_uhrzeit')->nullable();
            $table->bigInteger('77_regelmaessige_aufgaben')->nullable();
            $table->text('bemerkung')->nullable();
            $table->text('bild')->nullable();
        });

        Schema::create('ninox_mitarbeiter', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('vorname')->nullable();
            $table->text('nachname')->nullable();
            $table->text('spitzname')->nullable();
            $table->json('done_history')->nullable();
            $table->json('schichtbericht')->nullable();
            $table->json('bestellannahme')->nullable();
            $table->text('profilbild')->nullable();
            $table->text('status')->nullable();
            $table->json('monatsuebersicht')->nullable();
            $table->json('liefer_tour')->nullable();
            $table->json('liefer_tour2')->nullable();
            $table->json('zahlungen')->nullable();
            $table->json('kunden_historie')->nullable();
            $table->json('log')->nullable();
            $table->json('kassenbuch')->nullable();
        });

        Schema::create('ninox_lieferanten', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('name')->nullable();
            $table->text('beschreibung')->nullable();
            $table->text('bestelltag')->nullable();
            $table->text('bestell_art')->nullable();
            $table->text('liefertag')->nullable();
            $table->boolean('verraeumt_ware')->nullable();
            $table->text('ueber_neuen_inhaber_informiert_am')->nullable();
            $table->text('telefon')->nullable();
            $table->text('kontakt_e_mail')->nullable();
            $table->json('kontakte')->nullable();
            $table->json('dokument')->nullable();
            $table->json('bestellung')->nullable();
            $table->text('kundennummer')->nullable();
            $table->json('ordersatz_markt')->nullable();
            $table->json('ordersatz_lager')->nullable();
            $table->text('bestell_shop')->nullable();
            $table->boolean('beliefert_den_kehr_markt')->nullable();
            $table->boolean('beliefert_das_kehr_lager')->nullable();
            $table->boolean('beliefert_den_kolabri_markt')->nullable();
            $table->text('status')->nullable();
            $table->bigInteger('order_zahl')->nullable();
            $table->json('log')->nullable();
            $table->text('bestell_e_mail')->nullable();
            $table->bigInteger('mindestbestellwert_in_kaesten')->nullable();
            $table->text('bezeichnung')->nullable();
            $table->boolean('selbstabholung_durch_uns')->nullable();
        });

        Schema::create('ninox_kunden', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('kundennummer')->nullable();
            $table->bigInteger('plz')->nullable();
            $table->text('anrede')->nullable();
            $table->text('strasse_hausnummer')->nullable();
            $table->text('ort')->nullable();
            $table->text('bestell_art')->nullable();
            $table->boolean('zahlt_pfand')->nullable();
            $table->text('vorname')->nullable();
            $table->text('nachname')->nullable();
            $table->text('firmenname')->nullable();
            $table->text('telefon')->nullable();
            $table->text('bestellrhytmus')->nullable();
            $table->text('zahlungsart')->nullable();
            $table->text('alias')->nullable();
            $table->text('kundenart')->nullable();
            $table->text('kunde_von')->nullable();
            $table->text('status')->nullable();
            $table->text('kommi_erfolgt')->nullable();
            $table->text('notiz')->nullable();
            $table->json('bestellannahme')->nullable();
            $table->json('bestellannahme2')->nullable();
            $table->boolean('rechnung_per_email')->nullable();
            $table->boolean('rechnung_per_post')->nullable();
            $table->json('veranstaltung')->nullable();
            $table->text('e_mail')->nullable();
            $table->text('email_fuer_rechnungen')->nullable();
            $table->text('email_fuer_lieferbenachrichtigung')->nullable();
            $table->json('kontakte')->nullable();
            $table->json('lieferadressen')->nullable();
            $table->text('telefon_2')->nullable();
            $table->json('benachrichtigungen')->nullable();
            $table->text('preisgruppe')->nullable();
            $table->bigInteger('schluessel')->nullable();
            $table->decimal('reihenfolge_in_tour', 15, 6)->nullable();
            $table->text('bestell_text')->nullable();
            $table->text('kundenlogo')->nullable();
            $table->text('kundendaten')->nullable();
            $table->json('stammsortiment')->nullable();
            $table->bigInteger('regelmaessige_touren')->nullable();
            $table->json('abbuchungen')->nullable();
            $table->boolean('heimdienst_kalender_2025_erhalten')->nullable();
            $table->text('kundenkarteikarte')->nullable();
            $table->decimal('karteikarten_nr', 15, 6)->nullable();
            $table->json('zahlungen')->nullable();
            $table->json('kunden_historie')->nullable();
            $table->json('sepa_mandat')->nullable();
            $table->text('lieferhinweis')->nullable();
            $table->text('kundenkarteikarte_2')->nullable();
            $table->json('log')->nullable();
        });

        Schema::create('ninox_schluessel', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('bild')->nullable();
            $table->text('schluesselnummer')->nullable();
            $table->json('kunden')->nullable();
            $table->text('text_mehrzeilig')->nullable();
        });

        Schema::create('ninox_fest_inventar', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('artikelbezeichnung')->nullable();
            $table->bigInteger('artnrkehr')->nullable();
            $table->text('warenuntergruppe')->nullable();
            $table->bigInteger('ist_bestandteil_von')->nullable();
            $table->json('festbedarf_warenkorb')->nullable();
            $table->json('bestellannahme2')->nullable();
            $table->text('bild')->nullable();
            $table->bigInteger('artikel_pro_leiheinheit')->nullable();
            $table->decimal('vk_netto_einzeln_gh', 15, 6)->nullable();
            $table->decimal('vk_brutto_einzeln_gh', 15, 6)->nullable();
            $table->decimal('vk_leiheinheit_netto_gh', 15, 6)->nullable();
            $table->bigInteger('kosten_bei_beschaedigung_einzeln')->nullable();
            $table->decimal('vk_leiheinheit_netto_gam', 15, 6)->nullable();
            $table->decimal('vk_brutto_einzeln_gam', 15, 6)->nullable();
            $table->decimal('vk_netto_einzeln_gam', 15, 6)->nullable();
            $table->decimal('vk_brutto_einzeln_hd', 15, 6)->nullable();
            $table->decimal('vk_leiheinheit_netto_hd', 15, 6)->nullable();
            $table->decimal('vk_netto_einzeln_hd', 15, 6)->nullable();
            $table->decimal('bestand_leiheinheiten', 15, 6)->nullable();
            $table->text('beschreibung')->nullable();
            $table->bigInteger('bestand_einzeln')->nullable();
            $table->json('inventarisierte_exemplare')->nullable();
            $table->bigInteger('benoetigte_einheiten')->nullable();
            $table->decimal('stiegen', 15, 6)->nullable();
        });

        Schema::create('ninox_dokument', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('dokument')->nullable();
            $table->text('art')->nullable();
            $table->bigInteger('lieferanten')->nullable();
            $table->boolean('an_bank_uebermittelt')->nullable();
            $table->text('text_mehrzeilig')->nullable();
            $table->boolean('von_bank_bestaetigt')->nullable();
        });

        Schema::create('ninox_bestellung', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('anliefertag')->nullable();
            $table->boolean('bei_liefrant_bestellt')->nullable();
            $table->text('lieferschein')->nullable();
            $table->bigInteger('lieferanten')->nullable();
            $table->boolean('geliefert')->nullable();
            $table->text('bestell_schein')->nullable();
            $table->text('notiz')->nullable();
            $table->boolean('in_wawi_angelegt')->nullable();
            $table->text('lieferung_erfolgt_nach')->nullable();
            $table->bigInteger('dateien')->nullable();
            $table->text('googledrivelink')->nullable();
            $table->text('response')->nullable();
        });

        Schema::create('ninox_kassenbuch', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('datum')->nullable();
            $table->text('datum_uhrzeit')->nullable();
            $table->text('name')->nullable();
            $table->text('text_mehrzeilig')->nullable();
            $table->decimal('betrag', 15, 6)->nullable();
            $table->text('bild')->nullable();
            $table->text('bild_2')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
        });

        Schema::create('ninox_regelmaessige_touren', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('interval')->nullable();
            $table->text('wochentag')->nullable();
            $table->json('liefer_tour')->nullable();
            $table->json('kunden')->nullable();
            $table->text('status')->nullable();
            $table->text('art')->nullable();
            $table->text('suchabfrage')->nullable();
            $table->text('naechster_liefertag')->nullable();
        });

        Schema::create('ninox_bestellannahme', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('text_mehrzeilig')->nullable();
            $table->bigInteger('liefer_tour')->nullable();
            $table->text('lieferdatum')->nullable();
            $table->decimal('anzahl_vpe', 15, 6)->nullable();
            $table->text('art')->nullable();
            $table->boolean('rechnung')->nullable();
            $table->text('ankunftszeit_beim_kunden')->nullable();
            $table->text('abfahrtszeit_beim_kunden')->nullable();
            $table->text('bemerkung_fuer_rechnung_intern')->nullable();
            $table->text('status')->nullable();
            $table->decimal('preis', 15, 6)->nullable();
            $table->decimal('bezahlt', 15, 6)->nullable();
            $table->text('zahlstatus')->nullable();
            $table->text('lieferzeitpunkt')->nullable();
            $table->text('kunden_art')->nullable();
            $table->decimal('lieferschein_nr', 15, 6)->nullable();
            $table->text('lieferschein')->nullable();
            $table->bigInteger('kunden2')->nullable();
            $table->json('festbedarf_warenkorb')->nullable();
            $table->decimal('reihenfolge', 15, 6)->nullable();
            $table->text('rechnung_geschrieben')->nullable();
            $table->text('auswahl')->nullable();
            $table->text('belieferung_von')->nullable();
            $table->boolean('auch_geliefert')->nullable();
            $table->text('bild')->nullable();
            $table->bigInteger('abbuchungen')->nullable();
            $table->json('warenkorb_artikel')->nullable();
            $table->decimal('anzahl', 15, 6)->nullable();
            $table->decimal('trinkgeld', 15, 6)->nullable();
            $table->text('bestelltext')->nullable();
            $table->bigInteger('angenommen_durch')->nullable();
            $table->text('rechnung_f')->nullable();
            $table->text('googledrivelink')->nullable();
            $table->bigInteger('rechnungsnummer')->nullable();
            $table->text('liefer_anschrift')->nullable();
            $table->text('datum_der_veranstaltung')->nullable();
            $table->text('lieferzeit_von')->nullable();
            $table->text('lieferzeit_bis')->nullable();
            $table->text('unterschrift')->nullable();
            $table->bigInteger('anzahl_gaeste')->nullable();
            $table->text('artikel_zum_bestellen')->nullable();
            $table->boolean('sicherheitscheck')->nullable();
            $table->bigInteger('gehoert_zu_veranstaltung')->nullable();
            $table->bigInteger('benoetigte_liter')->nullable();
            $table->decimal('benoetigte_kaesten_24x0_33', 15, 6)->nullable();
            $table->decimal('benoetigte_kaesten_12x1_0_l', 15, 6)->nullable();
            $table->bigInteger('fest_inventar2')->nullable();
            $table->text('bemerkung_auf_rechnung_fuer_kunden_nur_email')->nullable();
            $table->text('fehlende_ware')->nullable();
            $table->bigInteger('artikel_hinzufuegen')->nullable();
            $table->json('zahlungen')->nullable();
            $table->json('kunden_historie')->nullable();
        });

        Schema::create('ninox_liefer_tour', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('datum')->nullable();
            $table->bigInteger('regelmaessige_touren')->nullable();
            $table->json('bestellannahme')->nullable();
            $table->text('status')->nullable();
            $table->text('tourbericht')->nullable();
            $table->text('text_mehrzeilig')->nullable();
            $table->bigInteger('fahrer')->nullable();
            $table->bigInteger('beifahrer')->nullable();
            $table->text('googledrivelink')->nullable();
            $table->text('feedback')->nullable();
            $table->bigInteger('fahrzeug')->nullable();
        });

        Schema::create('ninox_pfand_ruecknahme', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('3_10')->nullable();
            $table->text('beschreibung')->nullable();
            $table->bigInteger('1_50')->nullable();
            $table->bigInteger('2_30')->nullable();
            $table->text('status')->nullable();
            $table->bigInteger('0_08')->nullable();
            $table->bigInteger('3_42')->nullable();
            $table->bigInteger('2_40')->nullable();
            $table->bigInteger('3_30')->nullable();
            $table->bigInteger('4_50')->nullable();
            $table->bigInteger('2_38')->nullable();
            $table->bigInteger('2_46')->nullable();
            $table->bigInteger('2_70')->nullable();
            $table->bigInteger('3_00')->nullable();
            $table->bigInteger('0_15')->nullable();
            $table->bigInteger('0_25')->nullable();
            $table->text('bemerkung')->nullable();
        });

        Schema::create('ninox_hassia_rechner', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('12x0_75_l_glas_gebinde')->nullable();
            $table->bigInteger('6x1_0_l_glas')->nullable();
            $table->bigInteger('10x0_5_l_glas')->nullable();
            $table->bigInteger('12x0_75_l_pet')->nullable();
            $table->bigInteger('12x1_0_l_pet_1')->nullable();
            $table->text('datum')->nullable();
            $table->text('ort')->nullable();
            $table->bigInteger('12x1_0_l_pet_2')->nullable();
            $table->bigInteger('pet_cycle')->nullable();
            $table->bigInteger('bionade_12x0_33')->nullable();
            $table->bigInteger('gastro_0_25')->nullable();
            $table->bigInteger('gastro_0_75')->nullable();
        });

        Schema::create('ninox_lieferadressen', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('standort')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('name')->nullable();
            $table->text('text_mehrzeilig')->nullable();
        });

        Schema::create('ninox_kassen_umsatz', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('tag')->nullable();
            $table->decimal('bar_umsatz', 15, 6)->nullable();
            $table->decimal('ec_umsatz', 15, 6)->nullable();
            $table->decimal('gesamt_umsatz', 15, 6)->nullable();
            $table->decimal('ec_kassenschnitt', 15, 6)->nullable();
        });

        Schema::create('ninox_marktbestand', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('artikelname')->nullable();
            $table->bigInteger('bestand_markt')->nullable();
            $table->bigInteger('abnahmeintervall')->nullable();
            $table->text('artnummer')->nullable();
            $table->bigInteger('ordersatz_markt')->nullable();
            $table->bigInteger('bestellvorschlag_markt')->nullable();
            $table->text('mhd_markt')->nullable();
            $table->bigInteger('bestand_lager')->nullable();
            $table->text('test')->nullable();
            $table->bigInteger('bestellvorschlag_lager')->nullable();
            $table->bigInteger('ordersatz_lager')->nullable();
            $table->bigInteger('kasten_pro_palette')->nullable();
            $table->json('warenkorb_artikel')->nullable();
            $table->decimal('vk_brutto_markt', 15, 6)->nullable();
            $table->json('bestellannahme')->nullable();
            $table->bigInteger('artnrkehr')->nullable();
            $table->decimal('marge_in_vk_markt', 15, 6)->nullable();
            $table->decimal('marge_abholmarkt', 15, 6)->nullable();
            $table->text('mwst_satz')->nullable();
            $table->text('letzte_vk_preis_aenderung')->nullable();
            $table->decimal('grohandels_vk_netto', 15, 6)->nullable();
            $table->bigInteger('reihenfolge_bestellzettel')->nullable();
            $table->decimal('vk_einzelflasche_markt', 15, 6)->nullable();
            $table->decimal('vk_grosshandel_flasche', 15, 6)->nullable();
            $table->decimal('vk_heimdienst_flasche', 15, 6)->nullable();
            $table->decimal('flaschen_pro_kasten', 15, 6)->nullable();
            $table->text('letzte_bestandserfassung')->nullable();
            $table->boolean('heimdienst')->nullable();
            $table->bigInteger('warengruppen')->nullable();
            $table->bigInteger('artnrkolabrikasten')->nullable();
            $table->decimal('vk_brutto_kolabri', 15, 6)->nullable();
            $table->decimal('vk_netto_kolabri', 15, 6)->nullable();
            $table->text('letzte_bestandserfassung_lager')->nullable();
            $table->decimal('bestellvorschlag_lager_in_paletten', 15, 6)->nullable();
            $table->decimal('mwst_kolabri', 15, 6)->nullable();
            $table->decimal('grundpreis_l', 15, 6)->nullable();
            $table->decimal('inhalt_flasche', 15, 6)->nullable();
            $table->decimal('vk_einzelflasche_kolabri', 15, 6)->nullable();
            $table->text('produktart')->nullable();
            $table->text('artnrorderlager')->nullable();
            $table->boolean('kolabri_lieferservice')->nullable();
            $table->text('letztes_produktlisten_update')->nullable();
            $table->decimal('reihenfolge_im_markt', 15, 6)->nullable();
            $table->decimal('pfand', 15, 6)->nullable();
            $table->decimal('bestellvorschlag_markt_in_paletten', 15, 6)->nullable();
            $table->bigInteger('mindestbestand_markt')->nullable();
            $table->text('mhd_lager')->nullable();
            $table->boolean('preis_kolabri_anpassen')->nullable();
            $table->decimal('haltbarkeit_in_monaten', 15, 6)->nullable();
            $table->decimal('reihenfolge_im_lager', 15, 6)->nullable();
            $table->bigInteger('mindestbestand_lager')->nullable();
            $table->boolean('nicht_in_kolabri_vorhanden')->nullable();
            $table->text('warnung')->nullable();
            $table->boolean('produkteintrag_fehlerhaft')->nullable();
            $table->json('stammsortiment')->nullable();
            $table->decimal('ek_netto', 15, 6)->nullable();
            $table->decimal('ek_brutto', 15, 6)->nullable();
            $table->text('letzte_ek_preis_eintragung')->nullable();
            $table->bigInteger('vater_artikel')->nullable();
            $table->json('kind_artikel')->nullable();
            $table->text('fehler_art')->nullable();
            $table->text('fehlerbeschreibung')->nullable();
            $table->boolean('angebotsartikel')->nullable();
            $table->text('text')->nullable();
            $table->boolean('alle_felder_anzeigen')->nullable();
            $table->text('bestellbestandshinweis')->nullable();
            $table->text('warengruppe')->nullable();
            $table->decimal('angebotspreis', 15, 6)->nullable();
            $table->decimal('ek_hassia_pro_fuellung_in_cent', 15, 6)->nullable();
            $table->text('datum_hassia')->nullable();
            $table->decimal('ek_kasten_hassia', 15, 6)->nullable();
            $table->text('letzte_lieferanten_aenderung')->nullable();
            $table->json('log')->nullable();
            $table->boolean('preis_kehr_anpassen')->nullable();
            $table->text('ean')->nullable();
            $table->decimal('ek_netto_kk', 15, 6)->nullable();
            $table->decimal('ek_netto_winkels', 15, 6)->nullable();
            $table->text('datum_kk')->nullable();
            $table->text('datum_winkels')->nullable();
            $table->decimal('ek_netto_winkels_inkl_skonto', 15, 6)->nullable();
            $table->boolean('kk_gelistet')->nullable();
            $table->text('artnr_kk')->nullable();
            $table->boolean('winkels_gelistet')->nullable();
            $table->text('artnr_winkels')->nullable();
            $table->decimal('ek_netto_kraemer_gmbh', 15, 6)->nullable();
            $table->text('datum_kraemer')->nullable();
            $table->decimal('ek_netto_sonstiger', 15, 6)->nullable();
            $table->text('datum_sonstiger')->nullable();
            $table->text('artnr_sonstiger')->nullable();
            $table->text('name_sonstiger_lieferant')->nullable();
            $table->decimal('ek_netto_fuer_sie', 15, 6)->nullable();
            $table->text('datum_fuer_sie')->nullable();
            $table->boolean('fuer_sie_gelistet')->nullable();
            $table->boolean('zum_loeschen_markiert')->nullable();
            $table->text('bild')->nullable();
            $table->text('bestellbestandshinweis_markt')->nullable();
            $table->decimal('ek_netto_gut', 15, 6)->nullable();
            $table->text('datum_gut')->nullable();
            $table->boolean('kraemer_gelistet')->nullable();
            $table->boolean('gut_gelistet')->nullable();
            $table->text('artnr_kraemer')->nullable();
            $table->decimal('ek_netto_trinks', 15, 6)->nullable();
            $table->text('artnr_fuer_sie')->nullable();
            $table->boolean('trinks_gelistet')->nullable();
        });

        Schema::create('ninox_benachrichtigungen', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('ueberschrift')->nullable();
            $table->text('nachricht')->nullable();
            $table->text('datum_uhrzeit')->nullable();
            $table->text('art_der_benachrichtigung')->nullable();
            $table->text('mailbefehl')->nullable();
            $table->text('emailempfaenger')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('bemerkung')->nullable();
            $table->boolean('rechnung_manuell_nachgesendet')->nullable();
            $table->text('nachgesendet_am')->nullable();
            $table->text('nachricht_ohne_br')->nullable();
        });

        Schema::create('ninox_schichtbericht', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('text')->nullable();
            $table->text('bericht')->nullable();
            $table->text('datum')->nullable();
            $table->text('von')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->text('bis')->nullable();
            $table->bigInteger('arbeitszeit_ohne_pause')->nullable();
            $table->text('art')->nullable();
            $table->text('status')->nullable();
            $table->json('pausen')->nullable();
            $table->boolean('alle_ab_nachrichten_und_emails_geprueft_und_bestellungen_in_')->nullable();
            $table->bigInteger('arbeitszeit_mit_pause')->nullable();
            $table->bigInteger('pausendauer')->nullable();
            $table->boolean('kontrolliert')->nullable();
            $table->text('plan_von')->nullable();
            $table->text('plan_bis')->nullable();
            $table->bigInteger('arbeitszeit_soll')->nullable();
        });

        Schema::create('ninox_abbuchungen', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('abbuchungstag')->nullable();
            $table->text('status')->nullable();
            $table->json('bestellannahme')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('text')->nullable();
            $table->text('kunden_info_text')->nullable();
        });

        Schema::create('ninox_sepa_mandat', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('iban')->nullable();
            $table->text('name_zahlungspflichtiger')->nullable();
            $table->text('mandatsreferenz')->nullable();
            $table->text('mandatsdatum')->nullable();
            $table->text('lastschrifttyp')->nullable();
            $table->text('ausfuehrungsart')->nullable();
            $table->text('status')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('gescanntes_mandat')->nullable();
        });

        Schema::create('ninox_warenkorb_artikel', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->decimal('anzahl', 15, 6)->nullable();
            $table->bigInteger('marktbestand')->nullable();
            $table->bigInteger('bestellannahme')->nullable();
            $table->decimal('einzelpreis', 15, 6)->nullable();
            $table->decimal('gesamtpreis', 15, 6)->nullable();
            $table->decimal('pfandpreis_gesamt', 15, 6)->nullable();
            $table->decimal('pfandpreis_einzel', 15, 6)->nullable();
            $table->text('alternativ_name')->nullable();
        });

        Schema::create('ninox_belohnung', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('name')->nullable();
            $table->text('link_zum_bild')->nullable();
        });

        Schema::create('ninox_wasser', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('magnesium')->nullable();
            $table->bigInteger('calcium')->nullable();
            $table->decimal('natrium', 15, 6)->nullable();
            $table->text('wasser')->nullable();
            $table->decimal('kieselsaeure', 15, 6)->nullable();
            $table->decimal('kalium', 15, 6)->nullable();
        });

        Schema::create('ninox_festbedarf_warenkorb', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('fest_inventar')->nullable();
            $table->bigInteger('anzahl')->nullable();
            $table->bigInteger('veranstaltungsjahr')->nullable();
        });

        Schema::create('ninox_warengruppe', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('bezeichnung')->nullable();
            $table->text('id')->nullable();
            $table->json('marktbestand')->nullable();
            $table->text('order')->nullable();
            $table->json('unter_gruppen')->nullable();
            $table->bigInteger('ueber_gruppe')->nullable();
            $table->text('beschreibung')->nullable();
        });

        Schema::create('ninox_stammsortiment', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('marktbestand')->nullable();
            $table->decimal('anzahl', 15, 6)->nullable();
            $table->bigInteger('alle_x_tage')->nullable();
            $table->decimal('verbrauch_monat', 15, 6)->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('hinweis')->nullable();
        });

        Schema::create('ninox_arbeitsmaterial', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('arbeitsmittel')->nullable();
            $table->text('name')->nullable();
            $table->text('beschreibung')->nullable();
            $table->text('art')->nullable();
            $table->text('letzte_wartung')->nullable();
            $table->text('marke')->nullable();
            $table->text('anschaffung')->nullable();
            $table->text('zustand')->nullable();
            $table->boolean('eigentum')->nullable();
            $table->text('wo_zu_finden')->nullable();
            $table->bigInteger('quanta_costa')->nullable();
            $table->boolean('aktuell_in_reparatur')->nullable();
            $table->text('seit_wann')->nullable();
            $table->boolean('teile_bestellt')->nullable();
            $table->boolean('teile_da_gerade_am_reparieren')->nullable();
            $table->text('was_ist_kaputt_was_muss_gemacht_werden')->nullable();
            $table->boolean('da_muss_ein_profi_dran')->nullable();
            $table->text('termin_mit_profi')->nullable();
        });

        Schema::create('ninox_pausen', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('von')->nullable();
            $table->text('bis')->nullable();
            $table->bigInteger('dauer')->nullable();
            $table->bigInteger('schichtbericht')->nullable();
            $table->text('kommentar')->nullable();
        });

        Schema::create('ninox_monatsuebersicht', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('monat')->nullable();
            $table->bigInteger('jahr')->nullable();
            $table->text('berichte')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->text('uebersicht_ordentlich')->nullable();
        });

        Schema::create('ninox_log', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('logtitel')->nullable();
            $table->text('text')->nullable();
            $table->bigInteger('lieferanten')->nullable();
            $table->text('datum')->nullable();
            $table->text('datetime')->nullable();
            $table->text('uhrzeit')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->bigInteger('marktbestand')->nullable();
            $table->text('art_der_benachrichtigung')->nullable();
            $table->bigInteger('kunden')->nullable();
        });

        Schema::create('ninox_fahrzeug', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('name')->nullable();
            $table->text('beschreibung')->nullable();
            $table->bigInteger('max_kaesten')->nullable();
            $table->bigInteger('max_kaesten_inkl_sackkarre')->nullable();
            $table->text('kennzeichen')->nullable();
            $table->json('liefer_tour')->nullable();
        });

        Schema::create('ninox_zahlungen', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->bigInteger('bestellannahme')->nullable();
            $table->decimal('betrag', 15, 6)->nullable();
            $table->text('datum')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->text('zahlungsart')->nullable();
            $table->text('text')->nullable();
            $table->text('uhrzeit')->nullable();
        });

        Schema::create('ninox_buchhaltungs_dashboard', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('belege_zu_pruefen')->nullable();
            $table->bigInteger('umsaetze_zuordnen')->nullable();
            $table->bigInteger('vorschlaege_pruefen')->nullable();
        });

        Schema::create('ninox_kunden_historie', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->text('datum_uhrzeit')->nullable();
            $table->text('datum')->nullable();
            $table->text('uhrzeit')->nullable();
            $table->bigInteger('mitarbeiter')->nullable();
            $table->text('kontakt_kanal')->nullable();
            $table->text('richtung')->nullable();
            $table->text('betreff')->nullable();
            $table->text('gepsraechsprotokoll')->nullable();
            $table->text('ergebnis_status')->nullable();
            $table->text('naechster_schritt')->nullable();
            $table->bigInteger('kunden')->nullable();
            $table->bigInteger('bestellannahme')->nullable();
        });

        Schema::create('ninox_buchhaltungsuebersicht', function (Blueprint $table) {
            $table->unsignedBigInteger('ninox_id')->primary();
            $table->string('ninox_sequence')->nullable();
            $table->timestamp('ninox_created_at')->nullable();
            $table->timestamp('ninox_updated_at')->nullable();
            $table->bigInteger('belege_zu_pruefen')->nullable();
            $table->bigInteger('zahlungsvorschlaege_zu_pruefen')->nullable();
            $table->text('datum_uhrzeit')->nullable();
            $table->bigInteger('umsaetze_zuordnen')->nullable();
            $table->bigInteger('rechnungen_fuer_bestellungen_schreiben')->nullable();
            $table->text('text_mehrzeilig')->nullable();
            $table->bigInteger('ab_nachrichten')->nullable();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('ninox_veranstaltung');
        Schema::dropIfExists('ninox_veranstaltungstage');
        Schema::dropIfExists('ninox_veranstaltungsjahr');
        Schema::dropIfExists('ninox_aufgaben');
        Schema::dropIfExists('ninox_kontakte');
        Schema::dropIfExists('ninox_dokumente');
        Schema::dropIfExists('ninox_77_regelmaessige_aufgaben');
        Schema::dropIfExists('ninox_done_history');
        Schema::dropIfExists('ninox_mitarbeiter');
        Schema::dropIfExists('ninox_lieferanten');
        Schema::dropIfExists('ninox_kunden');
        Schema::dropIfExists('ninox_schluessel');
        Schema::dropIfExists('ninox_fest_inventar');
        Schema::dropIfExists('ninox_dokument');
        Schema::dropIfExists('ninox_bestellung');
        Schema::dropIfExists('ninox_kassenbuch');
        Schema::dropIfExists('ninox_regelmaessige_touren');
        Schema::dropIfExists('ninox_bestellannahme');
        Schema::dropIfExists('ninox_liefer_tour');
        Schema::dropIfExists('ninox_pfand_ruecknahme');
        Schema::dropIfExists('ninox_hassia_rechner');
        Schema::dropIfExists('ninox_lieferadressen');
        Schema::dropIfExists('ninox_kassen_umsatz');
        Schema::dropIfExists('ninox_marktbestand');
        Schema::dropIfExists('ninox_benachrichtigungen');
        Schema::dropIfExists('ninox_schichtbericht');
        Schema::dropIfExists('ninox_abbuchungen');
        Schema::dropIfExists('ninox_sepa_mandat');
        Schema::dropIfExists('ninox_warenkorb_artikel');
        Schema::dropIfExists('ninox_belohnung');
        Schema::dropIfExists('ninox_wasser');
        Schema::dropIfExists('ninox_festbedarf_warenkorb');
        Schema::dropIfExists('ninox_warengruppe');
        Schema::dropIfExists('ninox_stammsortiment');
        Schema::dropIfExists('ninox_arbeitsmaterial');
        Schema::dropIfExists('ninox_pausen');
        Schema::dropIfExists('ninox_monatsuebersicht');
        Schema::dropIfExists('ninox_log');
        Schema::dropIfExists('ninox_fahrzeug');
        Schema::dropIfExists('ninox_zahlungen');
        Schema::dropIfExists('ninox_buchhaltungs_dashboard');
        Schema::dropIfExists('ninox_kunden_historie');
        Schema::dropIfExists('ninox_buchhaltungsuebersicht');
    }
};