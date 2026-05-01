<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_packaging_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            $table->string('label', 100);
            $table->unsignedInteger('pieces_per_pack');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            // Total packs available (reduced permanently by breakage)
            $table->unsignedInteger('available_packs')->default(0);
            $table->timestamps();
            $table->index(['rental_item_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_packaging_units');
    }
};
