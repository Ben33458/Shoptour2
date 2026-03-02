<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_stops', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tour_id');
            $table->unsignedBigInteger('order_id');

            // Position of this stop within the tour route (ascending order)
            $table->unsignedInteger('stop_index');

            // Lifecycle: open → arrived → finished | skipped
            $table->string('status')->default('open');

            // Timestamps set by the driver on the mobile device
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('finished_at')->nullable();

            $table->timestamps();

            $table->foreign('tour_id')
                ->references('id')
                ->on('tours')
                ->cascadeOnDelete();

            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->restrictOnDelete();

            // An order appears at most once per tour
            $table->unique(['tour_id', 'order_id'], 'ts_tour_order_unique');

            // Queries to render the route in stop sequence
            $table->index(['tour_id', 'stop_index'], 'ts_tour_stop_index_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_stops');
    }
};
