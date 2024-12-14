<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleInvoice>
 */
class SaleInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cbteNum = rand(1, 100);
        return [
            'company_id' => 1,
            'customer_id' => 1,
            'voucher_id' => 1,
            'pto_vta' => 4,
            'cbte_desde' => $cbteNum,
            'cbte_hasta' => $cbteNum,
            'cbte_fch' => ''
        ];
    }
}
