<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kautionsregeln für Mietartikel.
 * Standard: keine Kaution. Nur für bestimmte Artikel / Kundengruppen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposit_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('name', 150);
            $table->enum('rule_type', ['none', 'fixed_per_item', 'private_only', 'risk_class'])
                ->default('none');
            // Deposit amount in milli-cents
            $table->unsignedBigInteger('amount_net_milli')->default(0);
            // Restrict to private customers only
            $table->boolean('private_only')->default(false);
            // Minimum risk class (if applicable)
            $table->string('min_risk_class', 50)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposit_rules');
    }
};
