<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wawi_artikel', function (Blueprint $table): void {
            $table->unsignedBigInteger('kArtikel')->primary();
            $table->string('cArtNr', 100)->nullable();
            $table->string('cBarcode', 100)->nullable();
            $table->decimal('fVKNetto', 15, 4)->nullable();
            $table->decimal('fEKNetto', 15, 4)->nullable();
            $table->string('cAktiv', 10)->nullable();
            $table->dateTime('dMod')->nullable();
            $table->string('cName', 500)->nullable();
            $table->text('cBeschreibung')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_kunden', function (Blueprint $table): void {
            $table->unsignedBigInteger('kKunde')->primary();
            $table->string('cKundenNr', 100)->nullable();
            $table->dateTime('dErstellt')->nullable();
            $table->string('cSperre', 10)->nullable();
            $table->string('cKundenGruppe', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_auftraege', function (Blueprint $table): void {
            $table->unsignedBigInteger('kAuftrag')->primary();
            $table->unsignedBigInteger('kKunde')->nullable()->index();
            $table->string('cAuftragsNr', 100)->nullable();
            $table->dateTime('dErstellt')->nullable();
            $table->integer('nAuftragStatus')->nullable();
            $table->string('cWaehrung', 10)->nullable();
            $table->string('cRgName', 255)->nullable();
            $table->string('cRgMail', 255)->nullable();
            $table->string('cRgStrasse', 255)->nullable();
            $table->string('cRgPLZ', 20)->nullable();
            $table->string('cRgOrt', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_auftragspositionen', function (Blueprint $table): void {
            $table->unsignedBigInteger('kAuftragPosition')->primary();
            $table->unsignedBigInteger('kAuftrag')->nullable()->index();
            $table->unsignedBigInteger('kArtikel')->nullable()->index();
            $table->string('cArtNr', 100)->nullable();
            $table->string('cName', 500)->nullable();
            $table->decimal('fAnzahl', 15, 4)->nullable();
            $table->decimal('fVkNetto', 15, 4)->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_rechnungen', function (Blueprint $table): void {
            $table->unsignedBigInteger('kRechnung')->primary();
            $table->unsignedBigInteger('kBestellung')->nullable()->index();
            $table->unsignedBigInteger('kKunde')->nullable()->index();
            $table->string('cRechnungsNr', 100)->nullable();
            $table->dateTime('dErstellt')->nullable();
            $table->string('cBezahlt', 10)->nullable();
            $table->integer('nRechnungStatus')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_lagerbestand', function (Blueprint $table): void {
            $table->unsignedBigInteger('kArtikel')->primary();
            $table->decimal('fLagerbestand', 15, 4)->nullable();
            $table->decimal('fVerfuegbar', 15, 4)->nullable();
            $table->decimal('fZulauf', 15, 4)->nullable();
            $table->tinyInteger('nLagerAktiv')->nullable();
            $table->timestamps();
        });

        Schema::create('wawi_warenlager', function (Blueprint $table): void {
            $table->unsignedBigInteger('kWarenLager')->primary();
            $table->string('cName', 255)->nullable();
            $table->string('cKuerzel', 50)->nullable();
            $table->tinyInteger('nAktiv')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wawi_warenlager');
        Schema::dropIfExists('wawi_lagerbestand');
        Schema::dropIfExists('wawi_rechnungen');
        Schema::dropIfExists('wawi_auftragspositionen');
        Schema::dropIfExists('wawi_auftraege');
        Schema::dropIfExists('wawi_kunden');
        Schema::dropIfExists('wawi_artikel');
    }
};
