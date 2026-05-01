<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Component/bundle logic: e.g. 1 Festzeltgarnitur = 1 Tisch + 2 Bänke
        Schema::create('rental_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('parent_rental_item_id');
            $table->foreign('parent_rental_item_id')
                ->references('id')
                ->on('rental_items')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('component_rental_item_id');
            $table->foreign('component_rental_item_id')
                ->references('id')
                ->on('rental_items')
                ->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
            $table->unique(['parent_rental_item_id', 'component_rental_item_id'], 'rental_components_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_components');
    }
};
