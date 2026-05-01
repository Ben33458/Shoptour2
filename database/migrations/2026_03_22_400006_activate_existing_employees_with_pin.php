<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Existing employees who already have a PIN are fully operational —
 * set them to 'active' so they are not locked out by the new onboarding gate.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->whereNotNull('pin_hash')
            ->where('onboarding_status', 'pending')
            ->update(['onboarding_status' => 'active']);
    }

    public function down(): void
    {
        // Not reversible (we don't know which were originally pending vs active)
    }
};
