<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Cambiar por lógica de autorización real (p.ej. Gate, Policy)
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'customer_id' => 'required|exists:customers,id',
            'status_id' => 'required|exists:statuses,id',
            'user_id' => 'sometimes|exists:users,id',
            'delivery_date' => 'nullable|date',
            'date' => 'required|string|max:100',
            
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.total' => 'required|numeric|min:0',
            // ... otras reglas para los ítems
        ];
    }
}