<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-21: Product image gallery.
 *
 * Each product can have multiple images ordered by sort_order.
 * The image with the lowest sort_order is considered the main image.
 * Files are stored via the "public" disk under products/{product_id}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path', 500);                       // relative path on public disk
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('alt_text', 255)->nullable();
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
