<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_rental_reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('event_occurrence_id')->index();
            $table->unsignedBigInteger('article_id')->index(); // FK zu products
            $table->decimal('quantity', 12, 3);
            $table->string('reservation_type', 10)->default('soft'); // soft, hard
            $table->string('reservation_status', 30)->default('requested'); // requested,reserved,confirmed,released,cancelled,conflict
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedBigInteger('source_offer_item_id')->nullable();
            $table->text('notes')->nullable();
            $table->index(['date_from', 'date_to', 'article_id'], 'reservation_range');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_rental_reservations');
    }
};
