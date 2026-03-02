<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->bigIncrements('id');

            // German postal code (PLZ), e.g. "80331"
            $table->string('postal_code');

            // City / Ort
            $table->string('city_name');

            // Optional district / Stadtteil
            $table->string('district_name')->nullable();

            $table->unsignedBigInteger('regular_delivery_tour_id');
            $table->timestamps();

            $table->foreign('regular_delivery_tour_id')
                ->references('id')
                ->on('regular_delivery_tours')
                ->cascadeOnDelete();

            // One tour may serve the same postal code only once
            $table->unique(['postal_code', 'regular_delivery_tour_id'], 'da_postal_tour_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};
