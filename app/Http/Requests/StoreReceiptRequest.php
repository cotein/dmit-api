<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Alta de un recibo, con o sin comprobantes imputados.
 *
 * - CON comprobantes: el cliente y la empresa salen del primero, como siempre.
 * - SIN comprobantes: es un pago a cuenta y el cliente y la empresa tienen que
 *   venir en el request (con saldo a favor del cliente).
 *
 * La validacion del store era inexistente: entraba el request crudo.
 */
class StoreReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyIds = $this->user()?->companies()->pluck('companies.id')->all() ?? [];

        return [
            // Pago a cuenta: sin comprobantes, cliente y empresa son obligatorios.
            'company_id' => [
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->whereIn('id', $companyIds),
            ],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')->whereIn('company_id', $companyIds),
            ],

            'invoicesToCancel' => 'nullable|array',
            'invoicesToCancel.*.key' => [
                'required_with:invoicesToCancel',
                'integer',
                Rule::exists('sale_invoices', 'id')->whereIn('company_id', $companyIds),
            ],
            'invoicesToCancel.*.importe' => 'nullable|numeric',
            'invoicesToCancel.*.toPayNow' => 'nullable|numeric',

            'documentsCancelation' => 'required|array|min:1',
            'documentsCancelation.*.payment_type_id' => 'nullable|integer',
            'documentsCancelation.*.import' => 'required|numeric|min:0.01',
            'documentsCancelation.*.imputation_date' => 'nullable|date',
            'documentsCancelation.*.number' => 'nullable|string|max:255',
            'documentsCancelation.*.comments' => 'nullable|string|max:255',
            'documentsCancelation.*.bank' => 'nullable',
            'documentsCancelation.*.cbu_id' => 'nullable',
            'documentsCancelation.*.chequeDate' => 'nullable',
            'documentsCancelation.*.chequeExpirate' => 'nullable',
            'documentsCancelation.*.chequeOwner' => 'nullable|string|max:255',

            'saldo' => 'nullable|numeric',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! empty($this->input('invoicesToCancel'))) {
                return;
            }

            if (empty($this->input('customer_id')) || empty($this->input('company_id'))) {
                $validator->errors()->add(
                    'customer_id',
                    'Un pago a cuenta necesita cliente y empresa.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'documentsCancelation.required' => 'El recibo necesita al menos un medio de pago.',
            'documentsCancelation.min' => 'El recibo necesita al menos un medio de pago.',
            'documentsCancelation.*.import.min' => 'El importe del pago tiene que ser mayor a cero.',
        ];
    }
}
