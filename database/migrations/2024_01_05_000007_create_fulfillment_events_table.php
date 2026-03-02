<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fulfillment_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('tour_stop_id');

            // Discriminator for the kind of event recorded:
            //   arrived            – driver marked stop as arrived
            //   finished           – driver closed stop; stock booking triggered
            //   item_delivered     – qty delivered for a specific order item
            //   item_not_delivered – qty NOT delivered with reason
            //   payment_recorded   – cash/card payment noted at door
            //   empties_adjusted   – empty returns adjustment (pos/neg)
            //   breakage_adjusted  – breakage adjustment
            //   note               – free-text note only
            $table->string('event_type');

            // Structured details: quantities, reasons, product IDs, etc.
            // Nullable for events with no payload (e.g. simple "arrived").
            $table->json('payload_json')->nullable();

            // The user (employee) who triggered this event (nullable = system/unknown)
            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Append-only: only created_at, no updated_at
            $table->dateTime('created_at');

            $table->foreign('tour_stop_id')
                ->references('id')
                ->on('tour_stops')
                ->cascadeOnDelete();

            $table->index(['tour_stop_id', 'event_type'], 'fe_stop_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_events');
    }
};
