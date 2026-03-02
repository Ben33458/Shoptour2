<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');

            // EAN-13, EAN-8, UPC-A, ITF-14, etc.
            $table->string('barcode')->unique();
            $table->string('barcode_type')->nullable();

            // Only one barcode should be primary per product (enforced at application layer)
            $table->boolean('is_primary')->default(false);

            // Validity window for barcode rotation / product relabelling
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();

            $table->timestamps();

            $table->foreign('product_id')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
