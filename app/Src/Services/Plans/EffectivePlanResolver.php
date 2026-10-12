<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\CompanyPlan;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve el plan efectivo de un CUIT. Es la única puerta por la que se pregunta
 * "qué plan tiene esta empresa", y la usan tanto la pantalla como el gating: si
 * cada uno lo resolviera por su cuenta, un día la pantalla diría "sin límite" y el
 * gating cortaría, o al revés.
 *
 * ORDEN DE RESOLUCIÓN (no se cambia sin cambiar la spec):
 *   1. suscripción activa y vigente -> ese plan, sin motivo de degradación;
 *   2. suscripción vencida, suspendida o cancelada -> plan gratuito CON motivo
 *      (un problema administrativo no puede cortar la facturación de golpe);
 *   3. sin suscripción -> plan gratuito SIN motivo (una cuenta nueva no está
 *      degradada: si mostrara motivo, se leería como una falla del sistema).
 *
 * El plan interno (el de los CUIT heredados) sale por el camino 1 y no tiene topes.
 *
 * CACHÉ: unos segundos, para que el gating no resuelva lo mismo dos veces en el
 * mismo pedido. `forget()` la invalida al cambiar de plan, así el cambio tiene
 * efecto en el acto y no cuando expire la caché.
 */
final class EffectivePlanResolver
{
    private const CACHE_SECONDS = 10;

    public function __construct(private readonly PlanCatalog $catalog)
    {
    }

    public function resolve(int $companyId): EffectivePlan
    {
        $data = Cache::remember(
            $this->cacheKey($companyId),
            self::CACHE_SECONDS,
            fn (): array => $this->build($companyId)->toCacheArray()
        );

        return EffectivePlan::fromCacheArray($data);
    }

    /**
     * Invalida la resolución de un CUIT. Se llama SIEMPRE después de un cambio de
     * plan (y al asignar el plan interno): el efecto tiene que ser inmediato.
     */
    public function forget(int $companyId): void
    {
        Cache::forget($this->cacheKey($companyId));
    }

    private function cacheKey(int $companyId): string
    {
        return 'plans.effective.' . $companyId;
    }

    /**
     * Construcción desde la base (sin caché). Es lo que corre el comando de
     * diagnóstico y lo que se cachea por unos segundos.
     */
    private function build(int $companyId): EffectivePlan
    {
        $subscription = CompanyPlan::query()->where('company_id', $companyId)->first();
        $free = $this->catalog->free();

        // Suscripción vigente: rige el plan asignado.
        if ($subscription !== null && $subscription->isCurrent()) {
            $plan = $this->catalog->findById((int) $subscription->plan_id);

            if ($plan !== null) {
                return $this->fromPlan(
                    $plan,
                    EffectivePlan::STATE_ACTIVE,
                    null,
                    $subscription->started_at?->toIso8601String(),
                    (int) $subscription->requested_plan_id ?: null
                );
            }

            // Con id de plan roto en la suscripción no se puede saber qué contrató.
            // Se cae al gratuito CON motivo: es degradación, no cuenta nueva.
            Log::warning('Plan de la suscripción inexistente: se resuelve como gratuito.', [
                'company_id' => $companyId,
                'plan_id' => $subscription->plan_id,
            ]);

            return $this->freeOrNothing($companyId, CompanyPlan::STATUS_EXPIRED, $free, $subscription);
        }

        // Vencida / suspendida / cancelada: gratuito con motivo.
        if ($subscription !== null) {
            return $this->freeOrNothing(
                $companyId,
                $subscription->lapseReason() ?? CompanyPlan::STATUS_EXPIRED,
                $free,
                $subscription
            );
        }

        // Sin suscripción: cuenta nueva, gratuito y SIN motivo.
        return $this->freeOrNothing($companyId, null, $free, null);
    }

    private function freeOrNothing(int $companyId, ?string $reason, ?Plan $free, ?CompanyPlan $subscription): EffectivePlan
    {
        if ($free === null) {
            // Sin plan gratuito cargado no hay política que aplicar. Se deja pasar
            // (equivalente a "sin tope") y se grita en el log: el umbral de vuelta
            // atrás del deploy es UNA factura rechazada por tope en un CUIT que
            // debería poder facturar, así que bloquear por un error de datos es el
            // peor resultado posible. El comando de diagnóstico reporta este estado.
            Log::error('No está cargado el plan gratuito: el corte por tope queda sin efecto.', [
                'company_id' => $companyId,
            ]);

            return new EffectivePlan(
                planId: null,
                code: Plan::CODE_FREE,
                name: 'Gratis',
                isInternal: true,
                state: EffectivePlan::STATE_FREE,
                reason: $reason,
                since: null,
                limits: [],
                features: [],
                requestedPlanId: (int) ($subscription->requested_plan_id ?? 0) ?: null,
            );
        }

        return $this->fromPlan(
            $free,
            EffectivePlan::STATE_FREE,
            $reason,
            null,
            (int) ($subscription->requested_plan_id ?? 0) ?: null
        );
    }

    private function fromPlan(Plan $plan, string $state, ?string $reason, ?string $since, ?int $requestedPlanId): EffectivePlan
    {
        return new EffectivePlan(
            planId: (int) $plan->id,
            code: (string) $plan->code,
            name: (string) $plan->name,
            isInternal: (bool) $plan->is_internal,
            state: $state,
            reason: $reason,
            since: $since,
            limits: $this->limitsOf($plan),
            features: $this->featuresOf($plan),
            requestedPlanId: $requestedPlanId,
        );
    }

    /**
     * @return array<string, int|null>
     */
    private function limitsOf(Plan $plan): array
    {
        $limits = [];

        foreach (PlanLimit::query()->where('plan_id', $plan->id)->get() as $row) {
            $limits[(string) $row->resource] = $row->limit_value === null ? null : (int) $row->limit_value;
        }

        return $limits;
    }

    /**
     * @return array<string, bool>
     */
    private function featuresOf(Plan $plan): array
    {
        $features = [];

        foreach (PlanFeature::query()->where('plan_id', $plan->id)->get() as $row) {
            $features[(string) $row->feature] = true;
        }

        return $features;
    }
}
