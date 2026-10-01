<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // users no tiene company_id: la relación es belongsToMany por company_user.
        $companyIds = $this->user()?->companies()->pluck('companies.id')->all() ?? [];

        return [
            'order_id' => [
                'sometimes', 'required',
                Rule::exists('orders', 'id')->whereIn('company_id', $companyIds),
            ],
            'payment_method_id' => [
                'sometimes', 'required',
                Rule::exists('payment_methods', 'id')->whereIn('company_id', $companyIds),
            ],
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_date' => 'sometimes|required|date_format:Y-m-d',
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed'])],
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
