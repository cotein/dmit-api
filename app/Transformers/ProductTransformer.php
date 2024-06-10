<?php

namespace App\Transformers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use League\Fractal\TransformerAbstract;

class ProductTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
        //
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
        //
    ];

    private function priceList($product): array
    {
        return $product->pricelist->map(function ($list) {
            return [
                'id' => $list->id,
                "name" => strtoupper($list->name),
                'pricelist_id' => $list->pricelist_id,
                'cost' => $list->pivot->price,
                'profit_percentage' => $list->pivot->profit_percentage,
                'profit_rate' => $list->pivot->profit_rate,
                'sale_price' => $this->calculateSalePrice($list->pivot->price, $list->pivot->profit_percentage)
            ];
        })->toArray();
    }

    private function calculateSalePrice($price, $profitPercentage): float
    {
        //Log::info('Price: ' . $price . ' ' . 'Profit: ' . $profitPercentage . ' ' . 'Sale Price: ' . $price + ($price * $profitPercentage / 100));
        //return $price + ($price * $profitPercentage / 100);
        return $price;
    }
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(Product $product)
    {
        return [
            'id' => $product->id,
            'name' => strtoupper($product->name),
            'price_list' => $this->priceList($product),
            'iva' => [
                'id' => $product->iva->id,
                'name' => strtoupper($product->iva->name),
                'percentage' => $product->iva->percentage,
                'afip_code' => (int) $product->iva->code
            ],
            'actions' => [],
            'aditional' => [
                'percentage' => 0,
                'aditional' => 0,
            ],

        ];
    }
}
