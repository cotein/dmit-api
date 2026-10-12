<?php

namespace App\Console\Commands;

use App\Src\Services\MercadoPago\MercadoPagoReconciliationService;
use Illuminate\Console\Command;

/**
 * Conciliación programada: pone de acuerdo el estado de DMIT con el de Mercado
 * Pago para todas las empresas.
 *
 * Toda la lógica vive en MercadoPagoReconciliationService, que también se usa
 * desde la app (POST /api/mercado-pago/reconcile) para la empresa activa: si el
 * servidor no tiene cron, esa es la única forma de que un cobro quede al día.
 *
 * Requiere que el scheduler esté corriendo (`php artisan schedule:run` desde
 * cron). En este proyecto el contenedor no lo trae: ver docs/pagos-mercadopago.md.
 */
class ReconcileMercadoPagoCharges extends Command
{
    protected $signature = 'mercadopago:conciliar {--limite= : Cuántos registros revisar por familia}';

    protected $description = 'Reconsulta en Mercado Pago los cobros pendientes, las suscripciones y los webhooks fallidos';

    public function handle(MercadoPagoReconciliationService $reconciliation): int
    {
        $limite = (int) ($this->option('limite') ?: config('mercadopago.reconciliation_limit'));

        // Primero lo barato: lo vencido no se consulta contra Mercado Pago.
        $vencidos = $reconciliation->expireOverdue();
        $this->info('Cobros vencidos marcados: ' . $vencidos);

        $cobros = $reconciliation->reconcileCharges(null, $limite);
        $this->info('Cobros revisados: ' . $cobros['revisados'] . ' · actualizados: ' . $cobros['actualizados']);

        $suscripciones = $reconciliation->reconcileSubscriptions(null, $limite);
        $this->info('Suscripciones revisadas: ' . $suscripciones['revisadas'] . ' · sincronizadas: ' . $suscripciones['sincronizadas']);

        $eventos = $reconciliation->retryFailedEvents();
        $this->info('Webhooks fallidos reintentados: ' . $eventos);

        return self::SUCCESS;
    }
}
