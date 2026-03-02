<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pfand_items', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Business rule: Einweg = disposable (non-returnable), Mehrweg = reusable (returnable)
            $table->string('pfand_typ');

            $table->string('bezeichnung');

            // All monetary values stored as milli-cents (1/1000 of a cent) for precision
            // e.g., 8.00 EUR = 8_000_000 milli-cents
            $table->integer('wert_netto_milli');
            $table->integer('wert_brutto_milli');
            $table->integer('wiederverkaeufer_wert_netto_milli');
            $table->integer('wiederverkaeufer_wert_brutto_milli');

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pfand_items');
    }
};
