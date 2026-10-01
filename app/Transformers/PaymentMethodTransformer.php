<?php

namespace App\Transformers;

use App\Models\PaymentMethod;
use League\Fractal\TransformerAbstract;

class PaymentMethodTransformer extends TransformerAbstract
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
    public function transform(PaymentMethod $method): array
    {
        return [
            'id' => (int) $method->id,
            'name' => $method->name,
        ];
    }
}
