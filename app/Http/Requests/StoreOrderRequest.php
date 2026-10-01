<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // El cliente y los productos tienen que ser de alguna empresa del usuario:
        // antes se validaba contra cualquier empresa y se aceptaba el company_id del body.
        $companyIds = $this->user()?->companies()->pluck('companies.id')->all() ?? [];

        return [
            // Aceptado por compatibilidad con el frontend actual. La empresa real la
            // resuelve App\Src\Support\CurrentCompany (ver BelongsToCompanyTrait).
            'company_id' => 'sometimes|integer',

            'customer_id' => ['required', Rule::exists('customers', 'id')->whereIn('company_id', $companyIds)],
            'status_id' => ['required', Rule::exists('statuses', 'id')],
            'user_id' => 'sometimes|exists:users,id',
            'delivery_date' => 'nullable|date',
            'date' => 'required|string|max:100',

            'items' => 'required|array|min:1',
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->whereIn('company_id', $companyIds)],
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.iva_percentage' => 'required|numeric|min:0',
            'items.*.discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'items.*.total' => 'required|numeric|min:0',
        ];
    }
}
