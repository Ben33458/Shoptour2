<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_price_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            $table->foreignId('rental_time_model_id')->constrained('rental_time_models')->cascadeOnDelete();
            $table->unsignedBigInteger('packaging_unit_id')->nullable();
            $table->foreign('packaging_unit_id')
                ->references('id')
                ->on('rental_packaging_units')
                ->nullOnDelete();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->enum('price_type', ['per_item', 'per_pack', 'per_set', 'flat'])->default('per_item');
            // Price in milli-cents (1_000_000 = 1 EUR)
            $table->unsignedBigInteger('price_net_milli');
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->boolean('requires_drink_order')->default(false);
            $table->timestamps();
            $table->index(['rental_item_id', 'rental_time_model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_price_rules');
    }
};
