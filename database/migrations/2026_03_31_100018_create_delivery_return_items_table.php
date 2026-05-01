<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Positionen in einer Rücknahme.
 *
 * Vollgut-Rückgabe Kästen:
 *   - Originalartikel: negative Menge
 *   - generated_fee_article_id = 58610 "Volle Kasten-Rückgabe"
 *
 * Vollgut-Rückgabe Fässer:
 *   - Originalartikel: negative Menge
 *   - generated_fee_article_id = 58611 "Volle Fass-Rückgabe"
 *
 * Fässer können NUR voll zurückgegeben werden; angebrochen = Leergut/Pfand.
 * MHD ist Pflicht bei Vollgut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_return_id')
                ->constrained('delivery_returns')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('article_id');
            $table->foreign('article_id')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
            // Negative quantity for returns
            $table->integer('quantity');
            $table->unsignedBigInteger('packaging_id')->nullable();
            $table->string('return_reason', 255)->nullable();
            // Pflichtfeld bei Vollgut-Rückgaben
            $table->date('best_before_date')->nullable();
            // Vollgut = immer wieder einlagerbar; Pflicht true bei Vollgut
            $table->boolean('is_restockable')->default(true);
            // Generated fee article (58610 for Kasten, 58611 for Fass)
            $table->unsignedBigInteger('generated_fee_article_id')->nullable();
            $table->foreign('generated_fee_article_id')
                ->references('id')
                ->on('products')
                ->nullOnDelete();
            $table->unsignedInteger('generated_fee_quantity')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('delivery_return_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_return_items');
    }
};
