<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUG-9 fix: Change customer_groups.price_adjustment_type from VARCHAR to
 * ENUM('none','fixed','percent') to enforce valid values at the DB level.
 *
 * MySQL ENUM is used directly via raw DDL because Blueprint::enum() requires
 * the column to be dropped and re-added, which loses the DEFAULT. Using
 * DB::statement() MODIFY COLUMN is safer for in-place type changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MODIFY COLUMN is MySQL-only; SQLite (local dev) enforces no types, skip.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement(
            "ALTER TABLE customer_groups
             MODIFY COLUMN price_adjustment_type
             ENUM('none','fixed','percent') NOT NULL DEFAULT 'none'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement(
            "ALTER TABLE customer_groups
             MODIFY COLUMN price_adjustment_type
             VARCHAR(20) NOT NULL DEFAULT 'none'"
        );
    }
};
