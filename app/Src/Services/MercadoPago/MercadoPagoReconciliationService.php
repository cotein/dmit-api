<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use App\Models\MercadoPagoCharge;
use App\Models\Subscription;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;

/**
 * Conciliación: pone de acuerdo lo que dice Mercado Pago con lo que dice DMIT.
 *
 * Existe porque los webhooks se pierden (timeout, deploy en el medio, firma mal
 * configurada) y el estado tiene que converger igual. Tiene DOS entradas que
 * comparten esta única implementación:
 *
 *  1. mercadopago:conciliar — el comando, pensado para el scheduler (todas las empresas).
 *  2. POST /api/mercado-pago/reconcile — desde la app, acotado a la empresa activa.
 *
 * La segunda no es decorativa: si el servidor no tiene cron, es la única forma
 * de que un cobro quede al día.
 */
class MercadoPagoReconciliationService
{
    public function __construct(
        private MercadoPagoClient $client,
        private MercadoPagoOAuthService $oauth,
        private MercadoPagoSettlementService $settlement,
        private MercadoPagoSubscriptionService $subscriptions,
        private MercadoPagoChargeService $charges,
    ) {
    }

    /**
     * Marca los cobros vencidos. Se corre siempre primero: lo vencido no se le
     * pregunta a Mercado Pago.
     */
    public function expireOverdue(?int $companyId = null): int
    {
        return $this->charges->expireOverdue($companyId);
    }

    /**
     * Reconsulta los cobros pendientes contra Mercado Pago.
     *
     * @param  int|null  $companyId  null = todas las empresas (comando)
     * @return array{revisados: int, actualizados: int}
     */
    public function reconcileCharges(?int $companyId = null, int $limit = 50): array
    {
        $query = MercadoPagoCharge::withoutGlobalScope(CompanyScope::class)
            ->whereIn('status', [MercadoPagoCharge::STATUS_PENDING, MercadoPagoCharge::STATUS_IN_PROCESS])
            ->whereNotNull('preference_id')
            ->where('created_at', '>=', now()->subDays(30))
            ->orderBy('created_at')
            ->limit($limit);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $cobros = $query->get();
        $actualizados = 0;

        foreach ($cobros as $charge) {
            $account = MercadoPagoAccount::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $charge->company_id)
                ->first();

            if (! $account || ! $account->isConnected()) {
                continue;
            }

            try {
                $pagos = $this->client->withToken($this->oauth->freshToken($account))->get('v1/payments/search', [
                    'external_reference' => $charge->external_reference,
                ]);

                foreach ($pagos['results'] ?? [] as $pago) {
                    if ($this->settlement->applyPayment($pago) !== null) {
                        $actualizados++;
                    }
                }
            } catch (\Throwable $e) {
                // Un cobro que falla no puede cortar la conciliación entera.
                Log::warning('Mercado Pago: error conciliando el cobro ' . $charge->id, ['error' => $e->getMessage()]);
            }
        }

        return ['revisados' => $cobros->count(), 'actualizados' => $actualizados];
    }

    /**
     * Sincroniza las suscripciones: un pago que no llega o una cancelación hecha
     * en el panel de Mercado Pago no avisan por ningún lado.
     *
     * @return array{revisadas: int, sincronizadas: int}
     */
    public function reconcileSubscriptions(?int $companyId = null, int $limit = 50): array
    {
        $query = Subscription::withoutGlobalScope(CompanyScope::class)
            ->whereNotNull('mp_preapproval_id')
            ->whereIn('status', [Subscription::STATUS_PENDING, Subscription::STATUS_AUTHORIZED, Subscription::STATUS_PAUSED])
            ->limit($limit);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $suscripciones = $query->get();
        $sincronizadas = 0;

        foreach ($suscripciones as $subscription) {
            try {
                $this->subscriptions->sync($subscription);
                $sincronizadas++;
            } catch (\Throwable $e) {
                Log::warning('Mercado Pago: error sincronizando la suscripción ' . $subscription->id, ['error' => $e->getMessage()]);
            }
        }

        return ['revisadas' => $suscripciones->count(), 'sincronizadas' => $sincronizadas];
    }

    /**
     * Reintenta los webhooks que quedaron en estado 'failed'.
     */
    public function retryFailedEvents(int $limit = 50): int
    {
        $eventos = \App\Models\MercadoPagoWebhookEvent::where('status', \App\Models\MercadoPagoWebhookEvent::STATUS_FAILED)
            ->where('attempts', '<', 5)
            ->limit($limit)
            ->get();

        foreach ($eventos as $evento) {
            app(MercadoPagoEventProcessor::class)->process($evento->id);
        }

        return $eventos->count();
    }
}
