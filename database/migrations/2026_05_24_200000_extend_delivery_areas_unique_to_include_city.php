<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            // Old constraint: (postal_code, regular_delivery_tour_id) — one PLZ per tour.
            // New constraint: (postal_code, city_name, regular_delivery_tour_id) — one PLZ+Ort per tour.
            // Needed because Ortsteile share a PLZ but belong to different tours
            // (e.g. 64372 Ober-Ramstadt vs. 64372 Modau).
            $table->dropUnique('da_postal_tour_unique');
            $table->unique(['postal_code', 'city_name', 'regular_delivery_tour_id'], 'da_postal_city_tour_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropUnique('da_postal_city_tour_unique');
            $table->unique(['postal_code', 'regular_delivery_tour_id'], 'da_postal_tour_unique');
        });
    }
};
