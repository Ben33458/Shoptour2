<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add nullable company_id to all core tables for multi-company isolation.
 *
 * Nullable so that existing rows (and test data) remain valid.
 * The application layer (CompanyMiddleware + scoped queries) enforces
 * company isolation at runtime.
 *
 * Tables touched: customers, warehouses, products, orders, tours,
 *                 invoices, payments
 *
 * No FK constraint on suppliers — added in the supplier migration.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'customers',
        'warehouses',
        'products',
        'orders',
        'tours',
        'invoices',
        'payments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->unsignedBigInteger('company_id')->nullable()->after('id');

                $t->foreign('company_id')
                    ->references('id')
                    ->on('companies')
                    ->nullOnDelete();

                // Speeds up "all X for company Y" queries
                $t->index('company_id', "{$table}_company_id_idx");
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $t) use ($table): void {
                $t->dropForeign(["{$table}_company_id_foreign"]);
                $t->dropIndex("{$table}_company_id_idx");
                $t->dropColumn('company_id');
            });
        }
    }
};
