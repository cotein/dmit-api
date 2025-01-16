<?php

namespace App\Transformers;

use App\Models\SaleInvoices;
use League\Fractal\TransformerAbstract;

class SaleInvoiceLastMonthInvoiced extends TransformerAbstract
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

    protected function items(SaleInvoices $si): array
    {
        return $si->items->map(function ($item) {
            return [
                'id' => $item->product->id,
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'total' => $item->total
            ];
        })->toArray();
    }

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(SaleInvoices $si)
    {
        return [
            'cbte_fch' => $si->cbte_fch,
            'customer_id' => $si->customer_id,
            'status_id' => $si->status_id,
            'voucher_id' => $si->voucher_id,
            'items' => $this->items($si),
            'total' => $si->items()->sum('total'),
        ];
    }
}
