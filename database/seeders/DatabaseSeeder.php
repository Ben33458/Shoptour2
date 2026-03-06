<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(TaxRateSeeder::class);

        // Default company (required for admin access via CompanyMiddleware)
        $company = Company::firstOrCreate(
            ['slug' => 'kolabri'],
            ['name' => 'Kolabri Getränke', 'active' => true]
        );

        // Default admin user — role/first_name not in $fillable, set directly
        $admin = User::firstOrCreate(
            ['email' => 'admin@kolabri.de'],
            [
                'first_name' => 'Admin',
                'last_name'  => 'Kolabri',
                'password'   => Hash::make('admin123'),
                'company_id' => $company->id,
                'active'     => true,
            ]
        );
        if ($admin->role !== 'admin') {
            $admin->role = 'admin';
            $admin->save();
        }
    }
}
