<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meli_id' => fake()->randomNumber(),
            'company_id' => fake()->numberBetween(1, 2),
            'name' => fake()->unique()->word . fake()->numberBetween(1, 5000),
            'code' => fake()->unique()->randomNumber(),
            'sub_title' => fake()->sentence,
            'description' => fake()->paragraph,
            'iva_id' => fake()->numberBetween(1, 6),
            'money_id' => 1,
            'priority_id' => fake()->randomNumber(1, 10),
            'published_meli' => fake()->boolean,
            'published_here' => fake()->boolean,
            'active' => fake()->boolean,
            'slug' => fake()->slug,
            'deleted_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'critical_stock' => fake()->boolean,
            'sale_by_meters' => false,
            'mts_by_unity' => 0,
            'apply_discount' => false,
            'apply_discount_amount' => 0,
            'apply_discount_percentage' => 0,
            'see_price_on_the_web' => fake()->boolean,
        ];
    }
}
