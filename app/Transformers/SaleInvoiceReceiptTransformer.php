<?php

namespace App\Transformers;

use App\Models\SaleInvoices;
use App\Src\Helpers\ZeroLeft;
use League\Fractal\TransformerAbstract;

class SaleInvoiceReceiptTransformer extends TransformerAbstract
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

    protected function getPreviousPayment(SaleInvoices $si)
    {
        return $si->receipts->map(function ($receipt) {

            if ($receipt->pivot->import_payment > 0) {
                return [
                    'percentagePayment' => $receipt->pivot->percentage_payment,
                    'importPayment' => $receipt->pivot->import_payment,
                    'percentagePaidHistory' => $receipt->pivot->percentage_paid_history,
                    'importPaidHistory' => $receipt->pivot->import_paid_history,
                ];
            }
        })->filter()->values();
    }

    public function transform(SaleInvoices $si)
    {
        $factor_multiplication = $si->isNotaCredito() ? -1 : 1;

        return [
            'id' => $si->id,
            'number' => $si->voucher->name . ' ' . ZeroLeft::print($si->pto_vta, 4) . '-' . ZeroLeft::print($si->cbte_desde, 8),
            'import' => $si->totalInvoiced() * $factor_multiplication,
            'previousPayment' => $this->getPreviousPayment($si),
        ];
    }
}
