<?php

namespace App\Transformers;

use App\Models\AfipVoucher;
use League\Fractal\TransformerAbstract;

class AfipVoucherTransformer extends TransformerAbstract
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
    public function transform(AfipVoucher $voucher)
    {
        return [
            'id' => $voucher->id,
            'value' => $voucher->id,
            'afip_code' => $voucher->afip_code,
            'name' => $voucher->name,
        ];
    }
}
