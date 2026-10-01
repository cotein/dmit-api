<?php

namespace App\Src\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Src\Repositories\PaymentRepository;

class PaymentService
{
    protected $paymentRepository;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function createPayment(array $data): Payment
    {
        // La empresa del pago es siempre la del pedido: nunca la que manda el cliente
        // ni "la primera del usuario" (un usuario puede tener varias empresas).
        $order = Order::findOrFail($data['order_id']);

        $data['company_id'] = $order->company_id;

        return $this->paymentRepository->create($data);
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        if (isset($data['order_id'])) {
            $data['company_id'] = Order::findOrFail($data['order_id'])->company_id;
        }

        $this->paymentRepository->update($payment, $data);

        return $payment->fresh();
    }

    public function deletePayment(Payment $payment): bool
    {
        return $this->paymentRepository->delete($payment);
    }
}
