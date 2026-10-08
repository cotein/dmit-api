<?php

namespace App\Transformers;

use App\Models\Receipt;
use App\Models\ReceiptPayment;
use App\Src\Constantes;
use App\Src\Helpers\ZeroLeft;
use League\Fractal\TransformerAbstract;

/**
 * Un pago recibido, listo para la grilla del facturador.
 *
 * El estado va como clave y como etiqueta: se deriva del recibo (anulado) y de
 * la imputacion sobre los comprobantes, porque el pago no tiene estado propio.
 */
class ReceiptPaymentTransformer extends TransformerAbstract
{
    public function transform(ReceiptPayment $payment): array
    {
        $receipt = $payment->receipt;
        $state = $this->resolveState($receipt);

        return [
            'id' => (int) $payment->id,
            'receipt_id' => (int) $payment->receipt_id,
            'receipt_number' => $receipt ? (int) $receipt->number : null,
            'date' => $payment->date ? (string) $payment->date : null,
            'amount' => (float) $payment->total,
            'payment_type_id' => $payment->payment_type_id ? (int) $payment->payment_type_id : null,
            'payment_type' => $payment->paymentType ? $payment->paymentType->name : null,
            'reference' => $payment->number,
            'description' => $payment->description,
            'bank' => $payment->bank ? $payment->bank->name : null,
            'cheque_date' => $payment->cheque_date ? (string) $payment->cheque_date : null,
            'cheque_expirate' => $payment->cheque_expirate ? (string) $payment->cheque_expirate : null,
            'cheque_owner' => $payment->cheque_owner,
            'customer' => $this->customer($receipt),
            'imputed' => $this->imputedInvoices($receipt),
            'status' => $state['key'],
            'status_label' => $state['label'],
            'receipt_total' => $receipt ? (float) $receipt->total : null,
            'receipt_balance' => $receipt ? (float) $receipt->saldo : null,
            'cancelled_at' => $receipt && $receipt->cancelled_at ? (string) $receipt->cancelled_at : null,
            'cancel_reason' => $receipt ? $receipt->cancel_reason : null,
        ];
    }

    private function customer($receipt): ?array
    {
        if (! $receipt || ! $receipt->customer) {
            return null;
        }

        return [
            'id' => (int) $receipt->customer->id,
            'name' => $this->customerName($receipt->customer),
            'afip_number' => $receipt->customer->afip_number,
        ];
    }

    private function customerName($customer): string
    {
        if ($customer->fantasy_name) {
            return (string) $customer->fantasy_name;
        }

        return trim($customer->name . ' ' . $customer->last_name);
    }

    /**
     * Comprobantes imputados por el recibo al que pertenece el pago.
     */
    private function imputedInvoices($receipt): array
    {
        if (! $receipt) {
            return [];
        }

        return $receipt->saleInvoices->map(function ($invoice) {
            return [
                'sale_invoice_id' => (int) $invoice->id,
                'number' => (int) $invoice->number,
                'comprobante' => $this->comprobante($invoice),
                'import_payment' => (float) $invoice->pivot->import_payment,
                'percentage_payment' => (float) $invoice->pivot->percentage_payment,
                'status_id' => (int) $invoice->status_id,
                'status_label' => $this->invoiceStatusLabel((int) $invoice->status_id),
            ];
        })->toArray();
    }

    private function comprobante($invoice): string
    {
        $voucher = $invoice->voucher ? $invoice->voucher->name : '';

        return $voucher . ' - ' . ZeroLeft::print($invoice->pto_vta, 4) . ' - ' . ZeroLeft::print($invoice->cbte_desde, 8);
    }

    private function invoiceStatusLabel(int $statusId): string
    {
        if ($statusId === Constantes::CANCELADA) {
            return 'Cancelada';
        }

        if ($statusId === Constantes::PARCIALMENTE_CANCELADA) {
            return 'Parcialmente cancelada';
        }

        return 'Adeudada';
    }

    /**
     * anulado   el recibo esta anulado
     * a_cuenta  el recibo no imputa a ningun comprobante
     * parcial   algun comprobante quedo parcialmente cancelado
     * cancelado todos los comprobantes imputados quedaron cancelados
     * imputado  imputa y ningun comprobante quedo parcial
     */
    private function resolveState(?Receipt $receipt): array
    {
        if ($receipt === null) {
            return ['key' => 'sin_recibo', 'label' => 'Sin recibo'];
        }

        if ($receipt->cancelled_at) {
            return ['key' => 'anulado', 'label' => 'Anulado'];
        }

        $invoices = $receipt->saleInvoices;

        if ($invoices->isEmpty()) {
            return ['key' => 'a_cuenta', 'label' => 'A cuenta'];
        }

        $partially = $invoices->contains(function ($invoice) {
            return (int) $invoice->status_id === Constantes::PARCIALMENTE_CANCELADA;
        });

        if ($partially) {
            return ['key' => 'parcial', 'label' => 'Parcial'];
        }

        $allCancelled = $invoices->every(function ($invoice) {
            return (int) $invoice->status_id === Constantes::CANCELADA;
        });

        if ($allCancelled) {
            return ['key' => 'cancelado', 'label' => 'Cancelado'];
        }

        return ['key' => 'imputado', 'label' => 'Imputado'];
    }
}
