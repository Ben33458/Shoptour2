<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');

            // ── Price adjustment ─────────────────────────────────────────────
            // Adjustment type applied on top of base_price when no explicit
            // customer_group_price or customer_price exists.
            //
            // "none"    – no adjustment; base_price is returned as-is
            // "fixed"   – add/subtract price_adjustment_fixed_milli (milli-cents)
            // "percent" – multiply by (1 + price_adjustment_percent_basis_points / 1_000_000)
            //             where 1 basis-point = 0.0001 (i.e. 10000 bp = 1 %)
            //
            // NOTE: Heimdienst is modelled as a regular customer group with an
            // adjustment; no special table is required.
            $table->string('price_adjustment_type')->default('none'); // none|fixed|percent

            // Used when price_adjustment_type = "fixed" (signed; negative = discount)
            $table->integer('price_adjustment_fixed_milli')->default(0);

            // Used when price_adjustment_type = "percent"
            // Stored as basis points where 10_000 bp = 1 % and 1_000_000 bp = 100 %
            // e.g. a 5 % surcharge = 50_000 bp; a 10 % discount = -100_000 bp
            $table->integer('price_adjustment_percent_basis_points')->default(0);

            // ── Customer classification ───────────────────────────────────────
            // Business customers may receive net pricing (VAT shown separately)
            $table->boolean('is_business')->default(false);

            // When true, deposit (Pfand) is not charged to members of this group
            $table->boolean('is_deposit_exempt')->default(false);

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
