<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('article_number', 50)->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('rental_item_categories')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('visible_in_shop')->default(false);
            $table->boolean('requires_event_order')->default(true);
            $table->enum('billing_mode', ['per_rental_period'])->default('per_rental_period');
            $table->enum('inventory_mode', ['unit_based', 'quantity_based', 'component_based', 'packaging_based'])->default('quantity_based');
            $table->enum('transport_class', ['small', 'normal', 'truck'])->default('normal');
            $table->boolean('allow_overbooking')->default(false);
            $table->unsignedBigInteger('damage_tariff_group_id')->nullable();
            $table->unsignedBigInteger('cleaning_fee_rule_id')->nullable();
            $table->unsignedBigInteger('deposit_rule_id')->nullable();
            $table->unsignedBigInteger('preferred_time_model_id')->nullable();
            // For quantity_based: total available quantity
            $table->unsignedInteger('total_quantity')->nullable();
            $table->string('unit_label', 50)->default('Stück');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->index(['active', 'requires_event_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_items');
    }
};
