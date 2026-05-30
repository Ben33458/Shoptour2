<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_weather_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->string('weather_type', 20); // forecast, actual, manual
            $table->string('source', 100)->nullable();
            $table->timestamp('captured_at');
            $table->timestamp('forecast_for_at')->nullable();
            $table->decimal('temperature_min', 5, 2)->nullable();
            $table->decimal('temperature_max', 5, 2)->nullable();
            $table->decimal('temperature_avg', 5, 2)->nullable();
            $table->decimal('precipitation_mm', 7, 2)->nullable();
            $table->decimal('wind_speed', 7, 2)->nullable();
            $table->string('weather_description', 255)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_weather_snapshots');
    }
};
