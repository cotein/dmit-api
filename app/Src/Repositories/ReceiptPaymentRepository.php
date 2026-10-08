<?php

namespace App\Src\Repositories;

use App\Models\ReceiptPayment;
use App\Src\Constantes;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Pagos recibidos.
 *
 * La pantalla lista PAGOS, no recibos: un recibo puede tener varios medios y
 * cada uno se muestra con su tipo, su referencia, su importe y su estado. Hoy
 * en la base hay un pago por recibo, pero la tabla ya soporta varios.
 *
 * El estado no vive en el pago: se deriva del recibo (anulado) y de la
 * imputación sobre los comprobantes (a cuenta, parcial, cancelado, imputado).
 */
class ReceiptPaymentRepository
{
    private const PER_PAGE_DEFAULT = 25;

    public function getPaginated(Request $request): LengthAwarePaginator
    {
        $perPage = (int) ($request->input('per_page') ?: self::PER_PAGE_DEFAULT);

        $query = ReceiptPayment::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_payments.receipt_id')
            ->where('receipts.company_id', $request->company_id)
            ->select('receipt_payments.*')
            ->with([
                'paymentType',
                'bank',
                'receipt.customer',
                'receipt.saleInvoices',
            ]);

        $this->applyFilters($query, $request);

        return $query
            ->orderByDesc('receipt_payments.date')
            ->orderByDesc('receipt_payments.id')
            ->paginate($perPage);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('receipt_payments.date', [$request->from, $request->to]);
        }

        if ($request->filled('customer_id')) {
            $query->where('receipts.customer_id', $request->customer_id);
        }

        if ($request->filled('payment_type_id')) {
            $query->where('receipt_payments.payment_type_id', $request->payment_type_id);
        }

        if ($request->filled('amount_from')) {
            $query->where('receipt_payments.total', '>=', $request->amount_from);
        }

        if ($request->filled('amount_to')) {
            $query->where('receipt_payments.total', '<=', $request->amount_to);
        }

        $this->applyStatusFilter($query, $request->input('status'));
    }

    /**
     * Filtro de estado derivado.
     *
     * anulado   el recibo esta anulado
     * activo    el recibo no esta anulado
     * a_cuenta  no imputa a ningun comprobante (saldo a favor del cliente)
     * parcial   algun comprobante quedo parcialmente cancelado
     * cancelado todos los comprobantes imputados quedaron cancelados
     * imputado  imputa y ningun comprobante quedo parcial
     */
    private function applyStatusFilter($query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        switch ($status) {
            case 'anulado':
                $query->whereNotNull('receipts.cancelled_at');
                break;

            case 'activo':
                $query->whereNull('receipts.cancelled_at');
                break;

            case 'a_cuenta':
                $query->whereNull('receipts.cancelled_at')
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('receipt_sale_invoices')
                            ->whereColumn('receipt_sale_invoices.receipt_id', 'receipts.id');
                    });
                break;

            case 'imputado':
                $this->whereHasImputation($query);
                break;

            case 'parcial':
                $this->whereHasImputation($query, Constantes::PARCIALMENTE_CANCELADA);
                break;

            case 'cancelado':
                $this->whereHasImputation($query, Constantes::CANCELADA);
                $this->whereHasNoImputationWithOtherStatus($query, Constantes::CANCELADA);
                break;
        }
    }

    /**
     * Recibos con al menos una imputacion (opcionalmente a un comprobante en
     * determinado estado).
     */
    private function whereHasImputation($query, ?int $statusId = null): void
    {
        $query->whereExists(function ($sub) use ($statusId) {
            $sub->select(DB::raw(1))
                ->from('receipt_sale_invoices')
                ->join('sale_invoices', 'sale_invoices.id', '=', 'receipt_sale_invoices.sale_invoices_id')
                ->whereColumn('receipt_sale_invoices.receipt_id', 'receipts.id');

            if ($statusId !== null) {
                $sub->where('sale_invoices.status_id', $statusId);
            }
        });
    }

    /**
     * Recibos SIN ninguna imputacion a un comprobante fuera de ese estado.
     * Se usa para exigir que TODOS los comprobantes hayan quedado cancelados.
     */
    private function whereHasNoImputationWithOtherStatus($query, int $statusId): void
    {
        $query->whereNotExists(function ($sub) use ($statusId) {
            $sub->select(DB::raw(1))
                ->from('receipt_sale_invoices')
                ->join('sale_invoices', 'sale_invoices.id', '=', 'receipt_sale_invoices.sale_invoices_id')
                ->whereColumn('receipt_sale_invoices.receipt_id', 'receipts.id')
                ->where('sale_invoices.status_id', '<>', $statusId);
        });
    }
}
