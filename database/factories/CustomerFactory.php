<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }

    // ... en CustomerFactory.php
    public function configure(): static
    {
        // Usa esta sintaxis:
        return $this->afterCreating(function (Customer $customer) {
            // Crea una dirección y la asocia con el cliente recién creado
            // como su "addressable" (entidad a la que pertenece la dirección).
            Address::factory()->for($customer, 'addressable')->create();
        });
    }
}
