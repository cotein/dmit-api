<?php

namespace App\Transformers;

use App\Models\SaleInvoices;
use League\Fractal\TransformerAbstract;

class SaleInvoiceCommentsTransformer extends TransformerAbstract
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

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(SaleInvoices $si)
    {

        return [
            $si->comments->comment,
        ];
    }
}
