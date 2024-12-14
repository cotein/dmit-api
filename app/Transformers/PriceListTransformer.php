<?php

namespace App\Transformers;

use App\Models\PriceList;
use League\Fractal\TransformerAbstract;

class PriceListTransformer extends TransformerAbstract
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
    public function transform(PriceList $priceList)
    {
        return [
            'value' => $priceList->id,
            'label' => $priceList->name,
            'profit_percentage' => $priceList->profit_percentage,
            'active' => $priceList->active,
        ];
    }
}
