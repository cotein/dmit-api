<?php

namespace App\Transformers;

use App\Models\OrderItem;
use League\Fractal\TransformerAbstract;

class OrderItemTransformer extends TransformerAbstract
{
    /**
     * Lista de relaciones que se incluyen POR DEFECTO.
     * @var array
     */
    protected array $defaultIncludes = [ // <-- CAMBIO AQUÍ
        'product'
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