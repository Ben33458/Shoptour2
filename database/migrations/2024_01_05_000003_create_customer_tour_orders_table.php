<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_tour_orders', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('regular_delivery_tour_id');

            // The stop sequence number for this customer within the tour.
            // Conventionally 10, 20, 30 … (allows gaps for insertion) or 1..n.
            // Lower number = earlier stop.
            $table->unsignedInteger('stop_order_number');

            $table->timestamps();

            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->cascadeOnDelete();

            $table->foreign('regular_delivery_tour_id')
                ->references('id')
                ->on('regular_delivery_tours')
                ->cascadeOnDelete();

            // A customer has exactly one stop number per tour
            $table->unique(
                ['customer_id', 'regular_delivery_tour_id'],
                'cto_customer_tour_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_tour_orders');
    }
};
