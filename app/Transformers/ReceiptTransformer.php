<?php

namespace App\Transformers;

use App\Models\Receipt;
use League\Fractal\TransformerAbstract;

class ReceiptTransformer extends TransformerAbstract
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
    public function transform(Receipt $r)
    {
        return [
            'id' => $r->id,

        ];
    }
}
