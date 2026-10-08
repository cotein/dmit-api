<?php

namespace App\Src\Services;

use App\Models\Receipt;
use App\Models\SaleInvoices;
use App\Src\Constantes;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anulación de recibos.
 *
 * Un recibo anulado no se borra: queda con su fecha, su motivo y el usuario que
 * lo anuló, y lo que se deshace es su efecto:
 *
 *  1. la imputación sobre los comprobantes (se quita el pivote del recibo),
 *  2. el acumulado pagado de cada comprobante afectado, que se recalcula con
 *     los recibos que siguen vivos,
 *  3. el estado del comprobante: cancelada, parcialmente cancelada o adeudada
 *     según lo que quede pagado,
 *  4. el pago en la cuenta corriente del cliente.
 *
 * Todo dentro de una transacción: o se deshace todo, o no se deshace nada.
 */
class ReceiptCancellationService
{
    public function cancel(Receipt $receipt, ?string $reason = null, ?int $userId = null): Receipt
    {
        if ($receipt->cancelled_at) {
            throw new RuntimeException('El recibo ya estaba anulado');
        }

        DB::transaction(function () use ($receipt, $reason, $userId) {
            $invoiceIds = $receipt->saleInvoices()->pluck('sale_invoices.id')->all();

            // La imputación de este recibo deja de existir. Los acumulados se
            // recalculan desde los recibos que quedan, así que no hace falta
            // arrastrar el histórico del recibo anulado.
            $receipt->saleInvoices()->detach();

            foreach ($invoiceIds as $invoiceId) {
                $this->recalculateInvoice((int) $invoiceId);
            }

            // El pago desaparece de la cuenta corriente del cliente; el recibo
            // queda con su motivo y su fecha para la auditoría.
            $receipt->cuentaCorriente()->delete();

            $receipt->cancelled_at = now();
            $receipt->cancel_reason = $reason;
            $receipt->cancelled_by = $userId;
            $receipt->save();
        });

        return $receipt->fresh();
    }

    /**
     * Deja el comprobante como estaba antes del recibo anulado: acumula lo que
     * siguen imputando los otros recibos y ajusta el estado.
     */
    private function recalculateInvoice(int $invoiceId): void
    {
        $invoice = SaleInvoices::find($invoiceId);

        if ($invoice === null) {
            return;
        }

        $pivots = DB::table('receipt_sale_invoices')
            ->join('receipts', 'receipts.id', '=', 'receipt_sale_invoices.receipt_id')
            ->where('receipt_sale_invoices.sale_invoices_id', $invoiceId)
            ->whereNull('receipts.cancelled_at')
            ->get([
                'receipt_sale_invoices.id',
                'receipt_sale_invoices.import_payment',
                'receipt_sale_invoices.percentage_payment',
            ]);

        $importPaid = (float) $pivots->sum('import_payment');
        $percentagePaid = (float) $pivots->sum('percentage_payment');

        foreach ($pivots as $pivot) {
            DB::table('receipt_sale_invoices')->where('id', $pivot->id)->update([
                'import_paid_history' => $importPaid,
                'percentage_paid_history' => $percentagePaid,
                'updated_at' => now(),
            ]);
        }

        if ($percentagePaid >= Constantes::CIENXCIEN) {
            $invoice->status_id = Constantes::CANCELADA;
        } elseif ($percentagePaid > 0) {
            $invoice->status_id = Constantes::PARCIALMENTE_CANCELADA;
        } else {
            $invoice->status_id = Constantes::ADEUDADA;
        }

        $invoice->save();
    }
}
