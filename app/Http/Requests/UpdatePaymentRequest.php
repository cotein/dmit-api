<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = Auth::user()->company_id;

        return [
            'order_id' => [
                'sometimes', 'required',
                Rule::exists('orders', 'id')->where('company_id', $companyId)
            ],
            'payment_method_id' => [
                'sometimes', 'required',
                Rule::exists('payment_methods', 'id')->where('company_id', $companyId)
            ],
            'amount' => 'sometimes|required|numeric|min:0.01',
            'payment_date' => 'sometimes|required|date_format:Y-m-d',
            'status' => ['sometimes', 'string', Rule::in(['pending', 'completed', 'failed'])],
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}