<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\CompanyPlan;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\User;

/**
 * Arma lo que ve la pantalla: el estado del plan del CUIT y el catálogo para elegir.
 *
 * Está separado de los servicios de decisión a propósito: acá se TRADUCE a la forma
 * del contrato (nombres, "sin límite", qué suma cada plan) y en los otros se decide.
 * Si el formato viviera en el controlador, cualquier cambio de Copy terminaría
 * tocando la lógica del corte.
 */
final class PlanOverview
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly EffectivePlanResolver $resolver,
        private readonly PlanUsage $usage,
        private readonly PlanAudience $audience,
    ) {
    }

    /**
     * GET /plan: qué plan rige, con qué topes, cuánto se usó y quién administra.
     *
     * @return array<string, mixed>
     */
    public function forCompany(int $companyId, ?User $user): array
    {
        $plan = $this->resolver->resolve($companyId);
        $period = PlanPeriod::current();

        $usage = [];

        foreach (PlanCatalog::countedResources() as $resource) {
            $usage[$resource] = $this->usage->snapshot($companyId, $resource, $period, $plan->limitFor($resource));
        }

        return [
            'plan' => [
                'code' => $plan->code,
                'name' => $plan->name,
                'is_internal' => $plan->isInternal,
            ],
            'state' => [
                'code' => $plan->state,
                'reason' => $plan->reason,
                'since' => $plan->since,
            ],
            'period' => $period->toArray(),
            'usage' => $usage,
            'features' => $this->featuresOf($plan),
            'can_manage' => $this->audience->canManage($user, $companyId),
            'pending_request' => $this->pendingRequest($companyId),
        ];
    }

    /**
     * GET /plan/catalog: los planes que se pueden elegir, cada uno con lo que SUMA
     * respecto del plan actual.
     *
     * Lo que suma se calcula acá y no en la pantalla: es la diferencia contra el plan
     * del CUIT, y la pantalla no tiene por qué saber comparar dos planes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogFor(int $companyId): array
    {
        $actual = $this->resolver->resolve($companyId);
        $actualPlan = $actual->planId !== null ? $this->catalog->findById($actual->planId) : null;
        $posicionActual = $actualPlan !== null ? (int) $actualPlan->position : 0;

        $planes = [];

        foreach ($this->catalog->sellable() as $plan) {
            $limits = [];

            foreach (PlanCatalog::countedResources() as $resource) {
                $limits[$resource] = $plan->limitFor($resource);
            }

            // Los usuarios y los puntos de venta se muestran aunque todavía no se
            // cuenten: son parte de lo que se contrata.
            $limits['users'] = $plan->limitFor('users');

            $planes[] = [
                'code' => (string) $plan->code,
                'name' => (string) $plan->name,
                'description' => $plan->description,
                'order' => (int) $plan->position,
                'price' => [
                    'amount' => (float) $plan->price_amount,
                    'currency' => (string) $plan->price_currency,
                ],
                'is_current' => (int) $plan->id === (int) $actual->planId,
                // Bajar de plan no se destaca y avisa qué implica antes de confirmar.
                'is_downgrade' => (int) $plan->position < $posicionActual,
                'limits' => $limits,
                'features' => $this->featuresOfPlan($plan),
                'gains' => $this->gains($plan, $actualPlan),
            ];
        }

        return $planes;
    }

    /**
     * Las cinco funciones, con verdadero o falso: la pantalla necesita las claves
     * completas, no sólo las que están.
     *
     * @return array<string, bool>
     */
    private function featuresOf(EffectivePlan $plan): array
    {
        return [
            PlanFeature::FEATURE_LOGO => $plan->hasFeature(PlanFeature::FEATURE_LOGO),
            PlanFeature::FEATURE_WATERMARK => $plan->hasFeature(PlanFeature::FEATURE_WATERMARK),
            PlanFeature::FEATURE_MERCADOPAGO => $plan->hasFeature(PlanFeature::FEATURE_MERCADOPAGO),
            PlanFeature::FEATURE_MERCADOLIBRE => $plan->hasFeature(PlanFeature::FEATURE_MERCADOLIBRE),
            PlanFeature::FEATURE_API => $plan->hasFeature(PlanFeature::FEATURE_API),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function featuresOfPlan(Plan $plan): array
    {
        return [
            PlanFeature::FEATURE_LOGO => $plan->hasFeature(PlanFeature::FEATURE_LOGO),
            PlanFeature::FEATURE_WATERMARK => $plan->hasFeature(PlanFeature::FEATURE_WATERMARK),
            PlanFeature::FEATURE_MERCADOPAGO => $plan->hasFeature(PlanFeature::FEATURE_MERCADOPAGO),
            PlanFeature::FEATURE_MERCADOLIBRE => $plan->hasFeature(PlanFeature::FEATURE_MERCADOLIBRE),
            PlanFeature::FEATURE_API => $plan->hasFeature(PlanFeature::FEATURE_API),
        ];
    }

    /**
     * Qué suma este plan respecto del actual, en frases cortas.
     *
     * Sólo lo que SUMA: repetir lo que el plan actual ya tiene convierte la tarjeta
     * en una lista de letra chica que nadie lee.
     *
     * @return array<int, string>
     */
    private function gains(Plan $plan, ?Plan $actual): array
    {
        $gains = [];

        foreach (PlanCatalog::countedResources() as $resource) {
            $suyo = $plan->limitFor($resource);
            $deHoy = $actual?->limitFor($resource);
            $etiqueta = $resource === PlanCatalog::RESOURCE_REMITOS ? 'remitos' : 'facturas';

            if ($suyo === null && $deHoy !== null) {
                $gains[] = 'Pasás a emitir ' . $etiqueta . ' sin límite por mes';
            } elseif ($suyo !== null && ($deHoy === null || $suyo > $deHoy)) {
                $gains[] = 'Pasás de ' . ($deHoy === null ? 'sin límite' : $deHoy . ' a ' . $suyo) . ' ' . $etiqueta . ' por mes';
            }
        }

        $features = [
            PlanFeature::FEATURE_LOGO => 'Sumás tu logo en los comprobantes',
            PlanFeature::FEATURE_WATERMARK => 'Sumás marca de agua en los comprobantes',
            PlanFeature::FEATURE_MERCADOPAGO => 'Sumás cobros con Mercado Pago',
            PlanFeature::FEATURE_MERCADOLIBRE => 'Sumás Mercado Libre',
            PlanFeature::FEATURE_API => 'Sumás la API',
        ];

        foreach ($features as $feature => $frase) {
            if ($plan->hasFeature($feature) && ! ($actual?->hasFeature($feature) ?? false)) {
                $gains[] = $frase;
            }
        }

        return $gains;
    }

    /**
     * El pedido de cambio pendiente, si alguien que no administra lo dejó.
     *
     * @return array<string, mixed>|null
     */
    private function pendingRequest(int $companyId): ?array
    {
        $row = CompanyPlan::query()->where('company_id', $companyId)->first();

        if ($row === null || $row->requested_plan_id === null) {
            return null;
        }

        $plan = $this->catalog->findById((int) $row->requested_plan_id);

        return [
            'plan_id' => (int) $row->requested_plan_id,
            'plan_code' => $plan?->code,
            'plan_name' => $plan?->name,
            'requested_at' => $row->requested_at?->toIso8601String(),
        ];
    }
}
