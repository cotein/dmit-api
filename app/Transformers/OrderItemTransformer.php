<?php

namespace App\Transformers;

use App\Models\OrderItem;
use League\Fractal\TransformerAbstract;

class OrderItemTransformer extends TransformerAbstract
{
    /**
     * El producto se expone como include OPCIONAL (?include=items.product).
     * No va por defecto: ProductTransformer asume que el producto tiene IVA y
     * lista de precios, y no queremos que /api/orders dependa de eso.
     *
     * @var array
     */
    protected array $availableIncludes = [
        'product',
    ];

    public function transform(OrderItem $item)
    {
        return [
            'id' => (int) $item->id,
            'quantity' => (float) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'total' => (float) $item->total,
        ];
    }

    /**
     * Incluir el Producto asociado.
     */
    public function includeProduct(OrderItem $item)
    {
        if ($item->product) {
            return $this->item($item->product, new ProductTransformer());
        }
        return $this->null();
    }
}