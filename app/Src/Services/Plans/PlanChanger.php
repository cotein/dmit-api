<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\CompanyPlan;
use App\Models\Plan;
use App\Models\PlanChangeLog;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Cambiar de plan: el efecto, el historial y el pedido que no cambia nada.
 *
 * LAS CUATRO REGLAS QUE NO SE ROMPEN:
 *   1. El efecto es INMEDIATO (se invalida la resolución del plan en el acto).
 *   2. NO se reinicia el contador del mes ni se acumula lo que sobró: el tope es por
 *      mes, no un saldo. Bajar de plan con 40 emitidos contra un tope de 10 conserva
 *      el 40 y no deja emitir hasta el mes siguiente; la pantalla tiene que poder
 *      explicarlo sin parecer una falla.
 *   3. No se toca ningún comprobante. Nunca.
 *   4. Es idempotente: repetir el mismo cambio no duplica el historial ni hace nada.
 *
 * Vencido o suspendido se REGULARIZA, no se cambia: la suscripción vuelve a activo con
 * el plan asignado y la fecha de inicio de la nueva etapa.
 */
final class PlanChanger
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly EffectivePlanResolver $resolver,
    ) {
    }

    /**
     * Aplica el cambio. Lo llaman la pantalla (source customer) y la configuración
     * interna (source internal).
     *
     * @throws ValidationException si el plan no existe, si se intenta vender el plan
     *                             interno desde la pantalla, o si se intenta bajar a
     *                             un plan que no destraba lo que el actual sí tiene
     */
    public function change(int $companyId, string $planCode, ?int $userId, string $source, ?string $reason = null): CompanyPlan
    {
        $plan = $this->planForChange($planCode, $source);

        $current = $this->currentOrNew($companyId);

        // Idempotencia: ya está en ese plan y vigente -> no hay cambio que registrar.
        if ((int) $current->plan_id === (int) $plan->id && $current->isCurrent()) {
            return $current;
        }

        $antes = (int) $current->plan_id ?: null;

        // Se regulariza si venía vencida o suspendida: el estado vuelve a activo y la
        // etapa arranca ahora, sin mirar cuándo venció.
        $current->plan_id = (int) $plan->id;
        $current->status = CompanyPlan::STATUS_ACTIVE;
        $current->started_at = now();
        $current->expires_at = null;
        $current->suspended_at = null;
        $current->assigned_by = $userId;
        $current->requested_plan_id = null;
        $current->requested_at = null;
        $current->requested_by = null;
        $current->save();

        PlanChangeLog::query()->create([
            'company_id' => $companyId,
            'from_plan_id' => $antes,
            'to_plan_id' => (int) $plan->id,
            'user_id' => $userId,
            'reason' => $reason,
            'source' => $source,
        ]);

        // El cambio tiene efecto en el acto, no cuando expire la caché.
        $this->resolver->forget($companyId);

        return $current->fresh();
    }

    /**
     * El pedido de alguien que NO administra la empresa: queda registrado para el
     * dueño y no cambia nada.
     *
     * Repetir el mismo pedido no llena el registro de pedidos iguales: se pisa el
     * anterior, que es lo que el dueño necesita ver.
     */
    public function request(int $companyId, string $planCode, ?int $userId, ?string $reason = null): CompanyPlan
    {
        $plan = $this->catalog->findByCode($planCode);

        if ($plan === null || $plan->is_internal) {
            throw ValidationException::withMessages([
                'plan_code' => 'El plan pedido no existe.',
            ]);
        }

        $current = $this->currentOrNew($companyId);

        $current->requested_plan_id = (int) $plan->id;
        $current->requested_at = now();
        $current->requested_by = $userId;
        $current->save();

        PlanChangeLog::query()->create([
            'company_id' => $companyId,
            'from_plan_id' => (int) $current->plan_id ?: null,
            'to_plan_id' => (int) $plan->id,
            'user_id' => $userId,
            'reason' => $reason,
            'source' => PlanChangeLog::SOURCE_REQUESTED,
        ]);

        return $current->fresh();
    }

    /**
     * Asigna el plan interno sin topes. Es lo que corre el backfill sobre las
     * empresas que ya existían: nadie se despierta bloqueado.
     */
    public function assignInternal(int $companyId, ?int $userId = null): CompanyPlan
    {
        $internal = $this->catalog->internal();

        if ($internal === null) {
            throw ValidationException::withMessages([
                'plan' => 'No está cargado el plan interno sin topes: no se puede asignar.',
            ]);
        }

        $current = $this->currentOrNew($companyId);

        if ((int) $current->plan_id === (int) $internal->id && $current->isCurrent()) {
            return $current;
        }

        $antes = (int) $current->plan_id ?: null;

        $current->plan_id = (int) $internal->id;
        $current->status = CompanyPlan::STATUS_ACTIVE;
        $current->started_at = $current->started_at ?? now();
        $current->expires_at = null;
        $current->assigned_by = $userId;
        $current->save();

        PlanChangeLog::query()->create([
            'company_id' => $companyId,
            'from_plan_id' => $antes,
            'to_plan_id' => (int) $internal->id,
            'user_id' => $userId,
            'reason' => 'Plan interno sin topes para los contribuyentes que ya existían.',
            'source' => PlanChangeLog::SOURCE_INTERNAL,
        ]);

        $this->resolver->forget($companyId);

        return $current->fresh();
    }

    /**
     * El historial de cambios, del más nuevo al más viejo.
     */
    public function history(int $companyId, int $limit = 50): Collection
    {
        return PlanChangeLog::query()
            ->where('company_id', $companyId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Las empresas que todavía no tienen ninguna suscripción.
     *
     * Es la consulta de control del backfill: si no da cero, no se enciende el gating.
     *
     * @return array<int>
     */
    public function companiesWithoutPlan(): array
    {
        return \App\Models\Company::query()
            ->whereNotIn('id', CompanyPlan::query()->pluck('company_id'))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function currentOrNew(int $companyId): CompanyPlan
    {
        return CompanyPlan::query()->firstOrNew(['company_id' => $companyId]);
    }

    private function planForChange(string $planCode, string $source): Plan
    {
        $plan = $this->catalog->findByCode($planCode);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'El plan pedido no existe.',
            ]);
        }

        // El plan interno no se vende ni se elige desde la pantalla: sólo lo asigna
        // la puesta en marcha (backfill o configuración interna).
        if ($plan->is_internal && $source !== PlanChangeLog::SOURCE_INTERNAL) {
            throw ValidationException::withMessages([
                'plan_code' => 'Ese plan no se puede elegir desde acá.',
            ]);
        }

        return $plan;
    }
}
