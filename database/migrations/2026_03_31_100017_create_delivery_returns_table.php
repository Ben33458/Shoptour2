<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rücknahmen bei normalen Lieferungen und Eventbelieferungen.
 * Gilt für: Pfandrückgaben, Vollgut-Rückgaben Kästen, Vollgut-Rückgaben Fässer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->foreign('order_id')
                ->references('id')
                ->on('orders')
                ->nullOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->foreign('customer_id')
                ->references('id')
                ->on('customers')
                ->restrictOnDelete();
            $table->unsignedBigInteger('driver_user_id')->nullable();
            $table->foreign('driver_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->dateTime('returned_at');
            $table->enum('return_type', ['deposit', 'full_goods'])->default('deposit');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'return_type']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_returns');
    }
};
