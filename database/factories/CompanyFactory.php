<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'last_name' => fake()->lastName(),
            'fantasyName' => fake()->name(),
            'dni' => fake()->numberBetween(20000000, 40000000),
            'afip_number' => fake()->randomNumber([20, 30, 27]) . fake()->numberBetween(20000000, 40000000) . fake()->numberBetween(1, 9)
        ];
    }
}
