<?php

namespace App\Transformers;

use App\Models\Customer;
use League\Fractal\TransformerAbstract;

class CustomerTransformer extends TransformerAbstract
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
    public function transform(Customer $customer)
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'last_name' => $customer->last_name,
            'afip_number' => $customer->afip_number,
            'afip_inscription' => [
                'id' => $customer->afipInscription->id,
                'name' => $customer->afipInscription->name
            ],
            'afip_document' => [
                'id' => $customer->afipDocument->id,
                'name' => $customer->afipDocument->name,
                'afip_code' => $customer->afipDocument->afip_code
            ],
            'status' => ($customer->active) ? 'Activo' : 'Inactivo',

        ];
    }
}
