<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Pricing\TaxRate;
use Illuminate\Database\Seeder;

/**
 * WP-19: Seed the standard German MwSt. rates.
 *
 * Uses firstOrCreate so the seeder is safe to run multiple times
 * (idempotent).
 */
class TaxRateSeeder extends Seeder
{
    public function run(): void
    {
        TaxRate::firstOrCreate(
            ['rate_basis_points' => 1900],
            ['name' => 'Regelsteuersatz (19 %)', 'active' => true]
        );

        TaxRate::firstOrCreate(
            ['rate_basis_points' => 700],
            ['name' => 'Ermäßigter Steuersatz (7 %)', 'active' => true]
        );
    }
}
