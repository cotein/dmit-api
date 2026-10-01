<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'     => \App\Models\User::factory(),
            'customer_id' => \App\Models\Customer::factory(),
            'company_id'  => \App\Models\Company::factory(),
            'status_id'   => \App\Models\Status::factory(),
            'voucher_id'  => \App\Models\AfipVoucher::factory(), // <-- AÑADE ESTA LÍNEA

            'total'       => $this->faker->randomFloat(2, 20, 1000),
            'parent_id'   => null,
        ];
    }
}
