<?php

namespace App\Transformers;

use App\Models\Product;
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
                "name" => "LISTA DE PRECIO 2",
                'pricelist_id' => $list->pricelist_id,
                'cost' => $list->pivot->price,
                'profit_percentage' => $list->pivot->profit_percentage,
                'profit_rate' => $list->pivot->profit_rate,
                'sale_price' => $list->pivot->price + ($list->pivot->price * $list->pivot->profit_percentage / 100)
            ];
        })->toArray();
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
            'name' => $product->name,
            'price_list' => $this->priceList($product),
            'iva' => [
                'id' => $product->iva->id,
                'name' => $product->iva->name,
                'percentage' => $product->iva->percentage
            ],
            'actions' => []

        ];
    }
}
