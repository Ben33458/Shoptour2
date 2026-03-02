<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regular_delivery_tours', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name');

            // How often this tour runs: weekly|bi-weekly|monthly
            $table->string('frequency');

            // Day on which the tour runs: Monday|Tuesday|...|Sunday
            $table->string('day_of_week');

            // Minimum number of Gebinde units in the order for this tour to be selectable.
            // 0 = no minimum (always selectable).
            $table->unsignedInteger('min_gebinde_qty')->default(0);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regular_delivery_tours');
    }
};
