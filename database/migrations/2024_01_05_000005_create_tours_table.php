<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->bigIncrements('id');

            // The concrete date this tour runs on
            $table->date('tour_date');

            // Which regular template this tour was generated from (nullable: ad-hoc tours)
            $table->unsignedBigInteger('regular_delivery_tour_id')->nullable();

            // The employee driving this tour.
            // Stored as a plain bigint — no FK constraint because the Employee module
            // is not yet implemented. Nullable until a driver is assigned.
            $table->unsignedBigInteger('driver_employee_id')->nullable();

            // Lifecycle: planned → in_progress → done | cancelled
            $table->string('status')->default('planned');

            $table->timestamps();

            $table->foreign('regular_delivery_tour_id')
                ->references('id')
                ->on('regular_delivery_tours')
                ->nullOnDelete();

            // Speeds up "show all tours for a given date" queries
            $table->index(
                ['tour_date', 'regular_delivery_tour_id'],
                'tours_date_regular_tour_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
