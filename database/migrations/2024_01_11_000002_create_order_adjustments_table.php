<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only log of closeout adjustments per order.
     * Types: leergut (empties return), bruch (breakage).
     */
    public function up(): void
    {
        Schema::create('order_adjustments', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->restrictOnDelete();

            // leergut = empties/pfand return, bruch = breakage
            $table->string('adjustment_type'); // leergut|bruch

            // Optional link to the product or gebinde being adjusted
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('gebinde_id')->nullable();

            // Snapshot of what was referenced (so deleting catalog items doesn't break history)
            $table->string('reference_label')->nullable(); // human-readable: "Kasten Pils 20x0.5L"

            // Quantity of units (bottles, crates, etc.) – always positive here; direction implied by type
            $table->integer('qty')->default(1);

            // Value adjustment in milli-cents (negative = credit, positive = charge)
            $table->bigInteger('amount_milli')->default(0);

            $table->text('note')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();

            // Append-only: no updated_at
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_adjustments');
    }
};
