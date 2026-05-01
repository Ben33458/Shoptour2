<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill customer_id on lexoffice_vouchers by matching lexoffice_contact_id.
 *
 * The LexofficePull import historically did not reliably set customer_id.
 * This migration performs a one-time JOIN-based update and is idempotent
 * (only touches rows where customer_id IS NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE lexoffice_vouchers lv
            INNER JOIN customers c ON c.lexoffice_contact_id = lv.lexoffice_contact_id
            SET lv.customer_id = c.id
            WHERE lv.customer_id IS NULL
              AND lv.lexoffice_contact_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        // Not reversible — restoring NULL customer_id would break data integrity
    }
};
