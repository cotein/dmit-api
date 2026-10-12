<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PlanChangeLog;
use App\Src\Services\Plans\PlanCatalog;
use App\Src\Services\Plans\PlanChanger;
use App\Src\Services\Plans\PlanPeriod;
use App\Src\Services\Plans\PlanSwitches;
use App\Src\Services\Plans\PlanUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuración INTERNA del corte de planes. No es pantalla de cliente.
 *
 * Acá se prenden y apagan los interruptores, se corre el saneamiento a mano, se
 * pide el recálculo y se asignan planes. Son las palancas de la puesta en marcha:
 * el corte se sube APAGADO, se asigna el plan sin topes a todos los CUIT, y recién
 * después se enciende — primero para un contribuyente de prueba y después para
 * todos.
 */
final class PlanAdminController extends Controller
{
    public function __construct(
        private readonly PlanSwitches $switches,
        private readonly PlanUsage $usage,
        private readonly PlanChanger $changer,
        private readonly PlanCatalog $catalog,
    ) {
    }

    /**
     * GET internal/plan/state
     *
     * Los interruptores, el estado del catálogo y la consulta de control que decide
     * si se puede encender: las empresas SIN suscripción tienen que dar cero.
     */
    public function state(): JsonResponse
    {
        $sinPlan = $this->changer->companiesWithoutPlan();

        return response()->json([
            'data' => [
                'switches' => $this->switches->state(),
                'catalog' => [
                    'plans' => $this->catalog->sellable()->count(),
                    'free_loaded' => $this->catalog->free() !== null,
                    'internal_loaded' => $this->catalog->internal() !== null,
                ],
                'companies_without_plan' => $sinPlan,
                'companies_without_plan_count' => count($sinPlan),
                'reservation_minutes' => $this->switches->reservationMinutes(),
                'period' => PlanPeriod::current()->toArray(),
            ],
        ]);
    }

    /**
     * PUT internal/plan/switches
     *
     * El interruptor general y el de un CUIT. `company_id` con `enabled` null borra
     * la excepción y vuelve a heredar del general.
     */
    public function switches(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'global' => ['nullable', 'boolean'],
            'company_id' => ['nullable', 'integer'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('global', $datos) && $datos['global'] !== null) {
            $this->switches->setGlobally((bool) $datos['global']);
        }

        if (! empty($datos['company_id'])) {
            $this->switches->setForCompany((int) $datos['company_id'], $datos['enabled'] ?? null);
        }

        return response()->json([
            'data' => $this->switches->state(),
            'message' => 'Interruptores actualizados.',
        ]);
    }

    /**
     * POST internal/plan/sanitize
     *
     * Libera las reservas vencidas. Corre solo cada pocos minutos; esto es para no
     * esperar cuando alguien se quedó con un lugar tomado.
     */
    public function sanitize(): JsonResponse
    {
        $liberadas = $this->usage->sanitizeExpired();

        return response()->json([
            'data' => ['released' => $liberadas],
            'message' => 'Reservas vencidas liberadas.',
        ]);
    }

    /**
     * POST internal/plan/recalculate
     *
     * El recálculo contra los comprobantes del periodo. INFORMA la diferencia y NO
     * la corrige: corregir en silencio taparía el bug que la produjo, que es
     * justamente lo que se quiere ver.
     */
    public function recalculate(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'company_id' => ['nullable', 'integer'],
            'period' => ['nullable', 'string', 'size:7'],
        ]);

        $period = isset($datos['period']) ? PlanPeriod::fromKey($datos['period']) : PlanPeriod::current();

        $empresas = isset($datos['company_id'])
            ? [(int) $datos['company_id']]
            : Company::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $informe = [];

        foreach ($empresas as $companyId) {
            foreach (PlanCatalog::countedResources() as $resource) {
                $resultado = $this->usage->recount($companyId, $resource, $period);

                if ($resultado['difference'] !== 0) {
                    $informe[] = $resultado + ['company_id' => $companyId];
                }
            }
        }

        return response()->json([
            'data' => [
                'period' => $period->key,
                'companies_checked' => count($empresas),
                'differences' => $informe,
            ],
            'message' => count($informe) === 0
                ? 'El contador coincide con los comprobantes del periodo en todas las empresas revisadas.'
                : 'Hay diferencias entre el contador y los comprobantes. Se informan y NO se corrigen.',
        ]);
    }

    /**
     * POST internal/plan/assign
     *
     * Asigna un plan a un CUIT desde la configuración interna (incluido el plan
     * interno sin topes).
     */
    public function assign(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'company_id' => ['required', 'integer'],
            'plan_code' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        $suscripcion = $this->changer->change(
            (int) $datos['company_id'],
            $datos['plan_code'],
            $request->user()?->id,
            PlanChangeLog::SOURCE_INTERNAL,
            $datos['reason'] ?? null
        );

        return response()->json([
            'data' => [
                'company_id' => (int) $suscripcion->company_id,
                'plan_id' => (int) $suscripcion->plan_id,
                'status' => $suscripcion->status,
            ],
            'message' => 'Plan asignado.',
        ]);
    }

    /**
     * POST internal/plan/backfill
     *
     * Asigna el plan interno sin topes a TODAS las empresas que no tengan
     * suscripción. Es el paso obligatorio ANTES de encender el corte: si se
     * encendiera primero, los clientes actuales quedarían limitados a diez facturas.
     */
    public function backfill(): JsonResponse
    {
        $asignadas = [];

        foreach ($this->changer->companiesWithoutPlan() as $companyId) {
            $this->changer->assignInternal($companyId);
            $asignadas[] = $companyId;
        }

        $restantes = $this->changer->companiesWithoutPlan();

        return response()->json([
            'data' => [
                'assigned' => $asignadas,
                'assigned_count' => count($asignadas),
                'remaining_without_plan' => count($restantes),
            ],
            'message' => count($restantes) === 0
                ? 'Todas las empresas tienen plan asignado: se puede encender el corte.'
                : 'Quedaron empresas sin plan: NO encender el corte todavía.',
        ]);
    }
}
