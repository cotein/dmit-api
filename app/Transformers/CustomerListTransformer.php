<?php

namespace App\Transformers;

use App\Models\Customer;
use App\Src\Traits\AddressTrait;
use League\Fractal\TransformerAbstract;

class CustomerListTransformer extends TransformerAbstract
{
    use AddressTrait;
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
            'fantasy_name' => $customer->fantasy_name,
            'cuit' => $customer->afip_number,
            'afipInscription' => $customer->afipInscription->name,
            'afipInscription_id' => $customer->afipInscription->id,
            'afipDocument' => $customer->afipDocument->name,
            'afipDocTipo' => $customer->afipDocument->afip_code,
            'address' => $this->address($customer),
            'status' => ($customer->active) ? 'Activo' : 'Inactivo',
        ];
    }
}
