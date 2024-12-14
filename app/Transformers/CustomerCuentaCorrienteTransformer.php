<?php

namespace App\Transformers;

use Carbon\Carbon;
use App\Models\SaleInvoices;
use App\Src\Helpers\ZeroLeft;
use App\Models\CustomerCuentaCorriente;
use League\Fractal\TransformerAbstract;

class CustomerCuentaCorrienteTransformer extends TransformerAbstract
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

    private function cuotaablesVoucher(CustomerCuentaCorriente $cc)
    {
        if ($cc->cuotaable instanceof SaleInvoices) {
            return $cc->cuotaable->voucher->name . ' ' . ZeroLeft::print($cc->cuotaable->pto_vta, 4) . '-' . ZeroLeft::print($cc->cuotaable->cbte_desde, 8);
        } else {
            return 'RECIBO ' . ZeroLeft::print(1, 4) . '-' . ZeroLeft::print($cc->cuotaable->number, 8);
        }
    }

    private function cuotaablesDate(CustomerCuentaCorriente $cc)
    {
        $date = null;

        if ($cc->cuotaable instanceof SaleInvoices) {
            $date = $cc->cuotaable->cbte_fch;
        } else {
            $date = $cc->cuotaable->created_at;
        }

        return Carbon::parse($date)->format('d-m-Y');
    }
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(CustomerCuentaCorriente $cc)
    {
        return [
            'id' => $cc->id,
            'number' => $cc->number,
            'company' => $cc->company->name,
            'customer' => $cc->customer->name,
            'voucher' => $this->cuotaablesVoucher($cc),
            'date' => $this->cuotaablesDate($cc),
            'sale' => $cc->sale,
            'pay' => $cc->pay,
            'isSaleInvoice' => ($cc->cuotaable instanceof SaleInvoices) ? true : false
        ];
    }
}
