<?php

namespace App\Transformers;

use App\Models\MercadoPagoCharge;
use League\Fractal\TransformerAbstract;

class MercadoPagoChargeTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'customer',
        'saleInvoice',
        'payments',
    ];

    public function transform(MercadoPagoCharge $charge): array
    {
        return [
            'id' => (int) $charge->id,
            'external_reference' => $charge->external_reference,
            'concept' => $charge->concept,
            'channel' => $charge->channel,
            'amount' => (float) $charge->amount,
            'currency' => $charge->currency,
            'title' => $charge->title,
            'description' => $charge->description,
            'status' => $charge->status,
            'status_detail' => $charge->status_detail,
            // El link que se comparte por WhatsApp o mail.
            'share_url' => $charge->shareUrl(),
            'init_point' => $charge->init_point,
            'preference_id' => $charge->preference_id,
            'customer_id' => $charge->customer_id ? (int) $charge->customer_id : null,
            'sale_invoice_id' => $charge->sale_invoice_id ? (int) $charge->sale_invoice_id : null,
            'expires_at' => $charge->expires_at?->toIso8601String(),
            'paid_at' => $charge->paid_at?->toIso8601String(),
            'created_at' => $charge->created_at?->toIso8601String(),
            // Rastro de la imputación (si ya se saldó el comprobante, etc.).
            'settlement' => $charge->metadata['settlement'] ?? null,
            // Devolución sobre un pago ya imputado: la UI tiene que avisarlo.
            'needs_review' => $charge->metadata['needs_review'] ?? null,
        ];
    }

    public function includeCustomer(MercadoPagoCharge $charge)
    {
        if ($charge->customer) {
            return $this->item($charge->customer, new CustomerTransformer());
        }
    }

    public function includeSaleInvoice(MercadoPagoCharge $charge)
    {
        if ($charge->saleInvoice) {
            return $this->item($charge->saleInvoice, new SaleInvoiceTransformer());
        }
    }

    public function includePayments(MercadoPagoCharge $charge)
    {
        if ($charge->payments) {
            return $this->collection($charge->payments, new MercadoPagoPaymentTransformer());
        }
    }
}
