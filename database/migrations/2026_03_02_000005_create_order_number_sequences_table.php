<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PROJ-4: Race-condition-safe daily order number sequences.
 *
 * Each row tracks the last-used sequence number for a given date.
 * The OrderNumberService uses SELECT FOR UPDATE to ensure uniqueness
 * even under concurrent requests.
 *
 * Format: B + YYMMDD + 3-digit sequence -> e.g. B260302001
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('last_sequence')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_number_sequences');
    }
};
