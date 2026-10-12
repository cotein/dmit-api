<?php

namespace App\Http\Requests;

use App\Models\MercadoPagoCharge;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de un cobro online.
 *
 * El `company_id` NO se acepta del cliente: lo pone el servidor desde la empresa
 * activa (BelongsToCompanyTrait). Aceptarlo sería permitir que un usuario genere
 * cobros a nombre de otra empresa.
 */
class StoreMercadoPagoChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'concept' => ['required', Rule::in([
                MercadoPagoCharge::CONCEPT_INVOICE,
                MercadoPagoCharge::CONCEPT_CURRENT_ACCOUNT,
                MercadoPagoCharge::CONCEPT_SALE,
                MercadoPagoCharge::CONCEPT_MANUAL,
            ])],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sale_invoice_id' => ['nullable', 'integer', 'exists:sale_invoices,id'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'El importe tiene que ser mayor a cero.',
            'concept.in' => 'El concepto del cobro no es válido.',
        ];
    }
}
