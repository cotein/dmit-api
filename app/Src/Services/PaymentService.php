<?php

namespace App\Src\Services;

use App\Models\Payment;
use App\Src\Repositories\PaymentRepository;
use Illuminate\Support\Facades\Auth;

class PaymentService
{
    protected $paymentRepository;

    public function __construct(PaymentRepository $paymentRepository)
    {
        $this->paymentRepository = $paymentRepository;
    }

    public function createPayment(array $data): Payment
    {
        $company = Auth::user()->companies()->first();
        $data['company_id'] = $company ? $company->id : null;
        return $this->paymentRepository->create($data);
    }

    public function updatePayment(Payment $payment, array $data): Payment
    {
        $this->paymentRepository->update($payment, $data);
        return $payment->fresh();
    }

    public function deletePayment(Payment $payment): bool
    {
        return $this->paymentRepository->delete($payment);
    }
}