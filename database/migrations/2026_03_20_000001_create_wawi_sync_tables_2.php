<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wawi_zahlungen', function (Blueprint $table): void {
            $table->unsignedBigInteger('kZahlung')->primary();
            $table->unsignedBigInteger('kRechnung')->nullable()->index();
            $table->unsignedBigInteger('kBestellung')->nullable()->index();
            $table->decimal('fBetrag', 15, 4)->nullable();
            $table->dateTime('dDatum')->nullable();
            $table->unsignedBigInteger('kZahlungsart')->nullable();
            $table->tinyInteger('nAnzahlung')->nullable();
            $table->text('cHinweis')->nullable();
            $table->integer('nZuweisungstyp')->nullable();
            $table->integer('nZahlungstyp')->nullable();
            $table->string('cExternalTransactionId', 255)->nullable();
            $table->unsignedBigInteger('kZahlungsabgleichUmsatz')->nullable();
            $table->integer('nZuweisungswertung')->nullable();
            $table->unsignedBigInteger('kGutschrift')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_rechnung_zahlungen', function (Blueprint $table): void {
            $table->unsignedBigInteger('kRechnung')->primary();
            $table->string('cRechnungsnummer', 100)->nullable();
            $table->decimal('fRechnungswert', 15, 4)->nullable();
            $table->dateTime('dBelegdatum')->nullable();
            $table->string('cKundennummer', 100)->nullable();
            $table->decimal('fBetrag', 15, 4)->nullable();
            $table->decimal('fMahngebuehr', 15, 4)->nullable();
            $table->unsignedBigInteger('kZahlungsart')->nullable();
            $table->decimal('fSkontowertInProzent', 10, 4)->nullable();
            $table->string('cZahlungsartbezeichnung', 255)->nullable();
            $table->decimal('fOffenerWert', 15, 4)->nullable();
            $table->integer('nZahlungStatus')->nullable();
            $table->string('cBestellnummer', 100)->nullable();
            $table->decimal('fAuftragswert', 15, 4)->nullable();
            $table->dateTime('dBestelldatum')->nullable();
            $table->string('cExterneBestellNr', 255)->nullable();
            $table->string('cVerwendungszweck', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_artikel_attribute', function (Blueprint $table): void {
            $table->unsignedBigInteger('kArtikelAttribut')->primary();
            $table->unsignedBigInteger('kArtikel')->nullable()->index();
            $table->string('cAttributName', 255)->nullable();
            $table->string('cWertVarchar', 1000)->nullable();
            $table->integer('nWertInt')->nullable();
            $table->decimal('fWertDecimal', 15, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_hersteller', function (Blueprint $table): void {
            $table->unsignedBigInteger('kHersteller')->primary();
            $table->string('cName', 255)->nullable();
            $table->string('cHomepage', 500)->nullable();
            $table->text('cBeschreibung')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_kategorien', function (Blueprint $table): void {
            $table->unsignedBigInteger('kKategorie')->primary();
            $table->unsignedBigInteger('kOberKategorie')->nullable()->index();
            $table->string('cAktiv', 10)->nullable();
            $table->integer('nSort')->nullable();
            $table->string('cName', 500)->nullable();
            $table->text('cBeschreibung')->nullable();
            $table->string('cUrlPfad', 1000)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_kategorien_artikel', function (Blueprint $table): void {
            $table->unsignedBigInteger('kArtikel');
            $table->unsignedBigInteger('kKategorie');
            $table->primary(['kArtikel', 'kKategorie']);
            $table->index('kKategorie');
            $table->timestamps();
        });

        Schema::create('wawi_zahlungsarten', function (Blueprint $table): void {
            $table->unsignedBigInteger('kZahlungsart')->primary();
            $table->string('cName', 255)->nullable();
            $table->tinyInteger('nLastschrift')->nullable();
            $table->tinyInteger('nAusliefernVorZahlung')->nullable();
            $table->tinyInteger('nMahnwesenAktiv')->nullable();
            $table->decimal('fSkontoWert', 10, 4)->nullable();
            $table->integer('nSkontoZeitraum')->nullable();
            $table->tinyInteger('nAktiv')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_versandarten', function (Blueprint $table): void {
            $table->unsignedBigInteger('kVersandArt')->primary();
            $table->string('cName', 255)->nullable();
            $table->decimal('fPrice', 15, 4)->nullable();
            $table->string('cAktiv', 10)->nullable();
            $table->decimal('fVKFreiAB', 15, 4)->nullable();
            $table->decimal('fMwSt', 10, 4)->nullable();
            $table->string('cTrackingUrlTemplate', 1000)->nullable();
            $table->tinyInteger('nExpress')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_preise', function (Blueprint $table): void {
            $table->unsignedBigInteger('kArtikel');
            $table->unsignedBigInteger('kKundenGruppe');
            $table->unsignedBigInteger('kKunde');
            $table->unsignedInteger('nAnzahlAb');
            $table->primary(['kArtikel', 'kKundenGruppe', 'kKunde', 'nAnzahlAb']);
            $table->decimal('fNettoPreis', 15, 4)->nullable();
            $table->decimal('fProzent', 10, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wawi_preise');
        Schema::dropIfExists('wawi_versandarten');
        Schema::dropIfExists('wawi_zahlungsarten');
        Schema::dropIfExists('wawi_kategorien_artikel');
        Schema::dropIfExists('wawi_kategorien');
        Schema::dropIfExists('wawi_hersteller');
        Schema::dropIfExists('wawi_artikel_attribute');
        Schema::dropIfExists('wawi_rechnung_zahlungen');
        Schema::dropIfExists('wawi_zahlungen');
    }
};
