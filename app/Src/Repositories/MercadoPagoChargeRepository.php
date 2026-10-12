<?php

namespace App\Src\Repositories;

use App\Models\MercadoPagoCharge;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Consultas de cobros de Mercado Pago.
 *
 * CompanyScope hace el filtro por empresa de forma automática (fail-closed):
 * acá no se filtra por company_id a mano para no dar la falsa sensación de que
 * el aislamiento depende de este código.
 */
class MercadoPagoChargeRepository
{
    public function getPaginated(array $filtros = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = MercadoPagoCharge::with(['customer', 'saleInvoice', 'payments'])
            ->orderByDesc('created_at');

        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (! empty($filtros['concept'])) {
            $query->where('concept', $filtros['concept']);
        }

        if (! empty($filtros['customer_id'])) {
            $query->where('customer_id', (int) $filtros['customer_id']);
        }

        if (! empty($filtros['sale_invoice_id'])) {
            $query->where('sale_invoice_id', (int) $filtros['sale_invoice_id']);
        }

        if (! empty($filtros['from'])) {
            $query->whereDate('created_at', '>=', $filtros['from']);
        }

        if (! empty($filtros['to'])) {
            $query->whereDate('created_at', '<=', $filtros['to']);
        }

        // Los cobros sin link (MP rechazó la preferencia) se pueden ocultar:
        // ensucian la lista y no son accionables.
        if (! empty($filtros['solo_con_link'])) {
            $query->whereNotNull('init_point');
        }

        return $query->paginate($perPage);
    }

    public function find(int $id): ?MercadoPagoCharge
    {
        return MercadoPagoCharge::with(['customer', 'saleInvoice', 'payments'])->find($id);
    }

    /**
     * Cobros aprobados o pendientes que hay que revalidar contra Mercado Pago
     * (los webhooks se pierden: esto es la red de seguridad).
     */
    public function pendientesParaConciliar(int $limit = 50)
    {
        return MercadoPagoCharge::whereIn('status', [
            MercadoPagoCharge::STATUS_PENDING,
            MercadoPagoCharge::STATUS_IN_PROCESS,
        ])
            ->whereNotNull('preference_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
