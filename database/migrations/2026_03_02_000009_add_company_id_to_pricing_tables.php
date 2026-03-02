<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-6 fix: Add company_id to all pricing tables for multi-tenant isolation.
 *
 * Tables affected:
 *   - customer_prices       (per-customer price overrides)
 *   - customer_group_prices (per-group price overrides)
 *   - tax_rates             (VAT rate definitions)
 *
 * All company_id columns are nullable for backward-compatibility with existing
 * rows. New rows must always supply company_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index('company_id', 'customer_prices_company_id_idx');
        });

        Schema::table('customer_group_prices', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index('company_id', 'customer_group_prices_company_id_idx');
        });

        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')->nullable()->after('id');
            $table->index('company_id', 'tax_rates_company_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_prices', function (Blueprint $table): void {
            $table->dropIndex('customer_prices_company_id_idx');
            $table->dropColumn('company_id');
        });

        Schema::table('customer_group_prices', function (Blueprint $table): void {
            $table->dropIndex('customer_group_prices_company_id_idx');
            $table->dropColumn('company_id');
        });

        Schema::table('tax_rates', function (Blueprint $table): void {
            $table->dropIndex('tax_rates_company_id_idx');
            $table->dropColumn('company_id');
        });
    }
};
