<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Pricing\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for Pricing\Customer — used in tests only.
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        static $counter = 0;
        $counter++;

        return [
            'customer_group_id'  => 1,
            'customer_number'    => 'K' . str_pad((string) ($counter + rand(10000, 99999)), 6, '0', STR_PAD_LEFT),
            'company_name'       => $this->faker->company(),
            'first_name'         => $this->faker->firstName(),
            'last_name'          => $this->faker->lastName(),
            'email'              => $this->faker->unique()->safeEmail(),
            'active'             => true,
            'price_display_mode' => 'gross',
            'delivery_status'    => 'normal',
        ];
    }
}
