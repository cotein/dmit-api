<?php

namespace App\Transformers;

use App\Models\Payment;
use League\Fractal\TransformerAbstract;

class PaymentTransformer extends TransformerAbstract
{
    protected array $availableIncludes = [
        'order',
        'paymentMethod'
    ];

    public function transform(Payment $payment): array
    {
        return [
            'id' => (int) $payment->id,
            'amount' => (float) $payment->amount,
            'payment_date' => $payment->payment_date->format('Y-m-d'),
            'status' => $payment->status,
            'reference' => $payment->reference_number,
        ];
    }

    public function includeOrder(Payment $payment)
    {
        if ($payment->order) {
            return $this->item($payment->order, new OrderTransformer());
        }
        return $this->null();
    }

    public function includePaymentMethod(Payment $payment)
    {
        if ($payment->paymentMethod) {
            return $this->item($payment->paymentMethod, new PaymentMethodTransformer());
        }
        return $this->null();
    }
}
