<?php

namespace App\Transformers;

use Carbon\Carbon;
use App\Models\Receipt;
use App\Src\Helpers\ZeroLeft;
use App\Src\Traits\AddressTrait;
use Illuminate\Support\Facades\Log;
use App\Src\Traits\ImageBase64Trait;
use App\Src\Traits\PaymentHistoryTrait;
use League\Fractal\TransformerAbstract;

class ReceiptTransformer extends TransformerAbstract
{

    use AddressTrait, ImageBase64Trait, PaymentHistoryTrait;
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

    private function getInvoices(Receipt $receipt)
    {
        return $receipt->saleInvoices->map(function ($invoice) {
            $importe_factura = $invoice->totalInvoiced();

            return [
                'number' => $invoice->number,
                'total' => $importe_factura,
                'comprobante' => $invoice->voucher->name . ' - ' . ZeroLeft::print($invoice->pto_vta, 4) . ' - ' . ZeroLeft::print($invoice->cbte_desde, 8),
                'importe' => $importe_factura,
                'percentage_payment' => $invoice->pivot->percentage_payment,
                'toPayNow' => $invoice->pivot->import_payment,
                'saldo' => $importe_factura - $invoice->pivot->import_paid_history,
                'percentage_paid_history' => $invoice->pivot->percentage_paid_history,
                'importe_previo_pagado' => $invoice->pivot->import_paid_history,
                'created_at' => Carbon::parse($invoice->pivot->created_at)->format('d-m-Y'),
            ];
        })->toArray();
    }

    private function documents_cancelation($receipt)
    {
        return $receipt->documents_cancelation->map(function ($doc) {
            return [
                'payment_type' => $doc->paymentType->name,
                'import' => $doc->total ? $doc->total : '',
                'number' => $doc->number ? $doc->number : '',
                'cta_cte' => $doc->cta_cte ? $doc->cta_cte : '',
                'description' => $doc->description ? $doc->description : '',
                'date' => $doc->created_at,
                'bank' => $doc->bank ? $doc->bank->name : '',
                'cheque_date' => $doc->cheque_date ? $doc->cheque_date : '',
                'chequeExpirate' => $doc->cheque_expirate_date ? $doc->cheque_expirate_date : '',
                'chequeOwner' => $doc->cheque_owner ? $doc->cheque_owner : '',
            ];
        });
    }
    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(Receipt $r)
    {
        $logo = $r->company->getMedia('logos')->first();

        if ($logo) {
            $logo_base64 = $this->convertImageToBase64($logo);
        } else {
            $logo_base64 = null;
        }
        return [

            'company' => [
                'name' => $r->company->name,
                'cuit' => $r->company->number,
                'address' => $this->address($r->company),
                'phone1' => $r->company->phone1,
                'phone2' => $r->company->phone2,
                'email' => $r->company->email,
                'web' => $r->company->web,
                'logo_base64' => $logo_base64,
            ],

            'receipt' => [
                'number' => $r->number,
                'pto_vta_receipt' => ($r->pto_vta_recibo) ? $r->pto_vta_recibo : 1,
                'date' => $r->created_at,
                'saldo' => $r->saldo,
                'total' => $r->total,
            ],

            'customer' => [
                'id' => $r->customer_id,
                'name' => $r->customer->name,
                'lastname' => $r->customer->last_name,
                'email' => $r->customer->email,
                'phone' => $r->customer->phone,
                'address' => $r->customer->address,
                'afip_number' => $r->customer->afip_number,
            ],
            'documentsCancelation' => $this->documents_cancelation($r),
            'invoicesToCancel' => $this->getInvoices($r),
        ];
    }
}
