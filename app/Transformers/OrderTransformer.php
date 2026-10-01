<?php

namespace App\Transformers;

use App\Models\Order;
use League\Fractal\TransformerAbstract;

class OrderTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'customer',
        'items',
        'user'
    ];

    // ... (el método transform no cambia) ...
    public function transform(Order $order)
    {
        return [
            'id' => (int) $order->id,
            'code' => $order->code,
            'delivery_date' => $order->delivery_date ? $order->delivery_date->format('Y-m-d') : null,
            'total' => (float) $order->total,
            'created_at' => $order->created_at->toDateTimeString(),
        ];
    }

    // ... (includeCustomer no cambia) ...
    public function includeCustomer(Order $order)
    {
        // ...
    }

    /**
     * Incluir los Items del pedido.
     */
    public function includeItems(Order $order)
    {
        // La magia ahora ocurre en OrderItemTransformer con $defaultIncludes
        return $this->collection($order->orderItems, new OrderItemTransformer(), 'items'); // <-- CAMBIO AQUÍ (se quitó el ->parseIncludes)
    }
    
    // ... (includeUser no cambia) ...
    public function includeUser(Order $order)
    {
        // ...
    }
}