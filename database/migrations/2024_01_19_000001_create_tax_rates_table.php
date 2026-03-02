<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WP-19: Create the tax_rates lookup table.
 *
 * Stores the MwSt. rates used on products.  All calculations use
 * rate_basis_points (1 basis-point = 0.01 %) to keep arithmetic
 * integer-safe.  Examples: 1900 = 19 %, 700 = 7 %.
 *
 * The products.tax_rate_id FK is not enforced at DB level (the Tax
 * module was intentionally kept out of scope in earlier WPs) but this
 * table provides the authoritative lookup for the admin UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('rate_basis_points'); // 1900 = 19 %, 700 = 7 %
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique('rate_basis_points');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
