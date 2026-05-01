<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rückgabescheine für Leihartikel.
 * Rückgabeschein ist für ALLE Leihartikel Pflicht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_return_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->unsignedBigInteger('driver_user_id')->nullable();
            $table->foreign('driver_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->dateTime('returned_at')->nullable();
            $table->string('location', 255)->nullable();
            $table->enum('status', ['open', 'partial', 'complete', 'reviewed', 'charged'])
                ->default('open')
                ->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_return_slips');
    }
};
