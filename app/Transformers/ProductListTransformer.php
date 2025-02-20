<?php

namespace App\Transformers;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use League\Fractal\TransformerAbstract;

class ProductListTransformer extends TransformerAbstract
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
                'product_id' => $list->pivot->product_id,
                "name" => strtoupper($list->name),
                'pricelist_id' => $list->pricelist_id,
                'cost' => $list->pivot->price - $list->pivot->profit_rate,
                'profit_percentage' => $list->pivot->profit_percentage,
                'profit_rate' => $list->pivot->profit_rate,
                'sale_price' => $list->pivot->price
            ];
        })->toArray();
    }

    private function calculateSalePrice($price, $profitPercentage): float
    {
        //Log::info('Price: ' . $price . ' ' . 'Profit: ' . $profitPercentage . ' ' . 'Sale Price: ' . $price + ($price * $profitPercentage / 100));
        //return $price + ($price * $profitPercentage / 100);
        return $price;
    }

    private function categories($product): array
    {

        return $product->categories->map(function ($category) {
            /* return [
                'id' => $category->id,
                'name' => strtoupper($category->name),
            ]; */
            return [
                $category->id,
            ];
        })->toArray();
    }

    private function tranfer_component($product): array
    {
        return $product->pricelist->map(function ($list) {
            return $list->id . '';
        })->toArray();
    }

    private function images($product): array
    {
        return $product->getMedia('products')->map(function ($image) {
            return [
                'id' => $image->id,
                'url' => $image->getUrl(),
                'order' => $image->order_column,
                'extension' => $image->mime_type,

            ];
        })->toArray();
    }

    public function transform(Product $product)
    {
        return [
            'id' => $product->id,
            'meli_id' => $product->meli_id,
            'company_id' => $product->company_id,
            'name' => strtoupper($product->name),
            'code' => $product->code,
            'sub_title' => $product->sub_title,
            'description' => $product->description,
            'money_id' => $product->money_id,
            'published_meli' => $product->published_meli,
            'published_here' => $product->published_here,
            'active' => $product->active,
            'slug' => $product->slug,
            'critical_stock' => $product->critical_stock,
            'sale_by_meters' => $product->sale_by_meters,
            'meters_by_unity' => ($product->mts_by_unity) ? $product->mts_by_unity : 0,
            'apply_discount' => $product->apply_discount,
            'apply_discount_amount' => $product->apply_discount_amount,
            'apply_discount_percentage' => $product->apply_discount_percentage,
            'see_price_on_the_web' => $product->see_price_on_the_web,
            'price_list' => $this->tranfer_component($product),
            'iva' => [
                'id' => $product->iva->id,
                'name' => strtoupper($product->iva->name),
                'percentage' => $product->iva->percentage,
                'afip_code' => (int) $product->iva->code
            ],
            'category' => $this->categories($product),
            'quantity' => ($product->stock_history->last()) ? $product->stock_history->last()->quantity : 0,
            'priority' => $product->priority_id,
            'cost' => $this->priceList($product)[0]['cost'],
            'lista_de_precios' => $this->priceList($product),
            //'images' => $this->images($product),
        ];
    }
}
