<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // users no tiene company_id: la relación es belongsToMany por company_user.
        // El pedido y el método de pago tienen que ser de alguna empresa del usuario.
        $companyIds = $this->user()?->companies()->pluck('companies.id')->all() ?? [];

        return [
            'order_id' => [
                'required',
                Rule::exists('orders', 'id')->whereIn('company_id', $companyIds),
            ],
            'payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->whereIn('company_id', $companyIds),
            ],
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date_format:Y-m-d',
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed'])],
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
