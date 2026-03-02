<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_fulfillments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tour_stop_id');
            $table->unsignedBigInteger('order_item_id');

            // Running tally: incremented by recordItemDelivery()
            $table->integer('delivered_qty')->default(0);

            // Running tally: incremented by recordItemNotDelivered()
            $table->integer('not_delivered_qty')->default(0);

            // Last reason recorded for non-delivery (overwritten, not appended).
            // Detailed history is in fulfillment_events.
            $table->string('not_delivered_reason')->nullable();

            // Driver / dispatcher notes (last written wins)
            $table->text('note')->nullable();

            // No created_at: the row is upserted on first delivery record.
            // updated_at is the standard Laravel timestamp.
            $table->dateTime('updated_at');

            $table->foreign('tour_stop_id')
                ->references('id')
                ->on('tour_stops')
                ->cascadeOnDelete();

            // order_item_id is intentionally NOT FK-constrained (mirrors order_items design)
            $table->index('order_item_id');

            // Only one fulfillment summary row per item per stop
            $table->unique(
                ['tour_stop_id', 'order_item_id'],
                'oif_stop_item_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_fulfillments');
    }
};
