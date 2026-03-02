<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-15 fix: Add company_id to deferred_tasks for multi-tenant isolation.
 *
 * Consistent with the project convention that every table has company_id.
 * Nullable to allow backward-compatible rows created before this migration
 * and for system-level tasks that are not tenant-specific.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deferred_tasks', function (Blueprint $table): void {
            $table->unsignedBigInteger('company_id')
                ->nullable()
                ->after('id');

            $table->index('company_id', 'deferred_tasks_company_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('deferred_tasks', function (Blueprint $table): void {
            $table->dropIndex('deferred_tasks_company_id_idx');
            $table->dropColumn('company_id');
        });
    }
};
