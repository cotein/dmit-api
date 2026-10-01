<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyIds = $this->user()?->companies()->pluck('companies.id')->all() ?? [];

        return [
            // Aceptado por compatibilidad; la empresa no se cambia desde el request.
            'company_id' => 'sometimes|integer',

            'customer_id' => ['sometimes', 'required', Rule::exists('customers', 'id')->whereIn('company_id', $companyIds)],
            'status_id' => ['sometimes', 'required', Rule::exists('statuses', 'id')],
            'user_id' => 'sometimes|exists:users,id',
            'delivery_date' => 'sometimes|nullable|date',
            'date' => 'sometimes|required|string|max:100',

            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => ['required_with:items', Rule::exists('products', 'id')->whereIn('company_id', $companyIds)],
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.iva_percentage' => 'required_with:items|numeric|min:0',
            'items.*.discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'items.*.total' => 'required_with:items|numeric|min:0',
        ];
    }
}
