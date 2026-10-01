<?php

namespace App\Transformers;

use App\Models\Order;
use League\Fractal\TransformerAbstract;

class OrderTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'customer',
        'items',
        'user',
    ];

    public function transform(Order $order)
    {
        return [
            'id' => (int) $order->id,
            'code' => $order->code,
            'status_id' => $order->status_id,
            'status' => $order->relationLoaded('status') ? optional($order->status)->name : null,
            'user_id' => $order->user_id,
            'delivery_date' => $order->delivery_date?->format('Y-m-d'),
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toDateTimeString(),
        ];
    }

    public function includeCustomer(Order $order)
    {
        if (! $order->customer) {
            return $this->null();
        }

        return $this->item($order->customer, new CustomerListTransformer(), 'customer');
    }

    /**
     * Incluir los Items del pedido.
     * La relación del modelo es Order::items() (no "orderItems").
     */
    public function includeItems(Order $order)
    {
        return $this->collection($order->items, new OrderItemTransformer(), 'items');
    }

    public function includeUser(Order $order)
    {
        if (! $order->user) {
            return $this->null();
        }

        return $this->item($order->user, new UserTransformer(), 'user');
    }
}
