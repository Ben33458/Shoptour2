<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_inventory_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('rental_item_id')->constrained('rental_items')->cascadeOnDelete();
            $table->string('inventory_number', 50)->unique();
            $table->string('serial_number', 100)->nullable();
            $table->string('title');
            $table->enum('status', ['available', 'reserved', 'in_use', 'maintenance', 'defective', 'retired'])
                ->default('available')
                ->index();
            $table->text('condition_notes')->nullable();
            $table->string('location', 255)->nullable();
            $table->boolean('preferred_for_booking')->default(false);
            $table->string('sync_source', 50)->nullable();
            $table->string('sync_source_id', 100)->nullable();
            $table->timestamps();
            $table->index(['rental_item_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_inventory_units');
    }
};
