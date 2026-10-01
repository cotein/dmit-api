<?php

namespace App\Src\Repositories;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentRepository
{
    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Payment::with(['order', 'paymentMethod'])->latest()->paginate($perPage);
    }

    public function findById(int $id): ?Payment
    {
        return Payment::with(['order', 'paymentMethod'])->find($id);
    }

    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): bool
    {
        return $payment->update($data);
    }

    public function delete(Payment $payment): bool
    {
        return $payment->delete();
    }
}