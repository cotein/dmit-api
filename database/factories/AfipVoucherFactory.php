<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AfipVoucher>
 */
class AfipVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Añade aquí los campos que tu tabla 'vouchers' necesite.
            // Por ejemplo, si tienes un campo 'name' o 'code':
            'name' => 'Factura C',
        ];
    }
}
