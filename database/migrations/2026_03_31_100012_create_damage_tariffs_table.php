<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('damage_tariffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->enum('applies_to_type', ['rental_item', 'category', 'packaging_unit']);
            $table->unsignedBigInteger('applies_to_id');
            $table->string('name', 150);
            // Damage amount in milli-cents
            $table->unsignedBigInteger('amount_net_milli');
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['applies_to_type', 'applies_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damage_tariffs');
    }
};
