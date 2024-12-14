<?php

namespace App\Transformers;

use Carbon\Carbon;
use App\Models\SaleInvoices;
use App\Src\Helpers\ZeroLeft;
use League\Fractal\TransformerAbstract;

class SaleInvoicePrintTransformer extends TransformerAbstract
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
     * Obtener el número de cada iteración.
     *
     * @return int
     */
    protected function getCurrentIteration()
    {
        static $iteration = 0;
        $iteration++;
        return $iteration;
    }

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(SaleInvoices $si)
    {
        return [
            $this->getCurrentIteration(),
            "{$si->customer->name} {$si->customer->last_name}",
            $si->customer->afip_number,
            $si->voucher->name,
            Carbon::parse($si->cbte_fch)->format('d-m-Y'),
            ZeroLeft::print($si->pto_vta, 4) . ' - ' . ZeroLeft::print($si->cbte_desde, 8),
            $si->items->sum('neto_import'),
            $si->items->sum('iva_import'),
            $si->items->sum('total') + $si->items->sum('percep_iibb_import') + $si->items->sum('percep_iva_import'),
            $si->cae,
            Carbon::parse($si->cae_fch_vto)->format('d-m-Y'),
            $si->saleCondition->name,
            $si->status_id
        ];
    }
}
