<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reinigungskosten-Regeln.
 * Reinigung wird NICHT pauschal berechnet, nur bei Bedarf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cleaning_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->enum('applies_to_type', ['rental_item', 'category', 'packaging_unit', 'inventory_unit'])
                ->default('rental_item');
            $table->unsignedBigInteger('applies_to_id')->nullable();
            $table->enum('fee_type', ['flat', 'per_item', 'per_pack', 'per_unit'])->default('flat');
            // Fee in milli-cents
            $table->unsignedBigInteger('amount_net_milli');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['applies_to_type', 'applies_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleaning_fee_rules');
    }
};
