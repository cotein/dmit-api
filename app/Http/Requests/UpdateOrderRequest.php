<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Lógica de autorización
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|required|exists:companies,id',
            'customer_id' => 'sometimes|required|exists:customers,id',
            'status_id' => 'sometimes|required|exists:statuses,id',
            'user_id' => 'sometimes|exists:users,id',
            'delivery_date' => 'sometimes|nullable|date',
            'date' => 'sometimes|required|string|max:100',

            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'required_with:items|numeric|min:0',
            'items.*.iva_percentage' => 'required_with:items|numeric|min:0',
            'items.*.total' => 'required_with:items|numeric|min:0',
        ];
    }
}