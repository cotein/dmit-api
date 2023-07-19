<?php

namespace App\Transformers;

use App\Models\AfipState;
use League\Fractal\TransformerAbstract;

class AfipStateTransformer extends TransformerAbstract
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
    public function transform(AfipState $state)
    {
        return [
            'value' => $state->id,
            'label' => strtoupper($state->name)
        ];
    }
}
