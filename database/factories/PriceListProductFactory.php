<?php

namespace Database\Factories;

use App\Models\PriceList;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PriceListProduct>
 */
class PriceListProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = fake()->numberBetween(100, 50000);
        $price = round($price, 2);

        $priceList = PriceList::inRandomOrder()->first();
        return [
            'pricelist_id' => $priceList->id,
            'product_id' => Product::inRandomOrder()->first()->id,
            'price' => $price,
            'profit_percentage' => $priceList->profit_percentage,
            'profit_rate' => 1,
            'created_at' => null,
            'updated_at' => null
        ];
    }
}
