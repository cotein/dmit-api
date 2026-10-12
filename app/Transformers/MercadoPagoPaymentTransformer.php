<?php

namespace App\Transformers;

use App\Models\MercadoPagoPayment;
use League\Fractal\TransformerAbstract;

class MercadoPagoPaymentTransformer extends TransformerAbstract
{
    public function transform(MercadoPagoPayment $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'mp_payment_id' => $payment->mp_payment_id,
            'status' => $payment->status,
            'status_detail' => $payment->status_detail,
            'amount' => (float) $payment->amount,
            'fee_amount' => $payment->fee_amount !== null ? (float) $payment->fee_amount : null,
            'net_amount' => $payment->net_amount !== null ? (float) $payment->net_amount : null,
            'payment_method_id' => $payment->payment_method_id,
            'payment_type' => $payment->payment_type,
            'installments' => $payment->installments,
            'payer_email' => $payment->payer_email,
            'approved_at' => $payment->approved_at?->toIso8601String(),
            'refunded_at' => $payment->refunded_at?->toIso8601String(),
        ];
    }
}
