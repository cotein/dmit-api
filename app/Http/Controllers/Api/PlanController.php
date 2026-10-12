<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlanChangeLog;
use App\Src\Services\Plans\PlanCatalog;
use App\Src\Services\Plans\PlanChanger;
use App\Src\Services\Plans\PlanOverview;
use App\Src\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El plan del CUIT activo: qué rige, cuánto se usó y cómo se cambia.
 *
 * TODO ES POR CONTRIBUYENTE, no por usuario: la empresa sale siempre de
 * CurrentCompany (el servidor decide, nunca el cliente), y dos usuarios del mismo
 * CUIT ven el mismo contador.
 *
 * Este controlador NO decide si se puede emitir: eso pasa en el camino de emisión,
 * con el contador real. Acá sólo se informa y se cambia de plan.
 */
final class PlanController extends Controller
{
    public function __construct(
        private readonly PlanOverview $overview,
        private readonly PlanChanger $changer,
        private readonly PlanCatalog $catalog,
        private readonly CurrentCompany $currentCompany,
    ) {
    }

    /**
     * GET plan
     *
     * El estado del plan y el consumo del periodo, con el motivo de degradación
     * cuando lo hay. Si el plan venció, `state.reason` lo dice y el plan que rige es
     * el gratuito: un problema administrativo no corta la facturación de golpe.
     */
    public function show(Request $request): JsonResponse
    {
        $companyId = $this->companyId();

        return response()->json([
            'data' => $this->overview->forCompany($companyId, $request->user()),
        ]);
    }

    /**
     * GET plan/catalog
     *
     * Los planes que se pueden elegir, cada uno con lo que suma respecto del actual.
     */
    public function catalog(): JsonResponse
    {
        return response()->json([
            'data' => $this->overview->catalogFor($this->companyId()),
        ]);
    }

    /**
     * POST plan/change
     *
     * Si quien pide administra la empresa, el cambio se aplica en el acto. Si no,
     * queda REGISTRADO como pedido y la respuesta lo dice: no es una falla, es un
     * pedido en cola para el dueño.
     *
     * VENCIDO O SUSPENDIDO SE REGULARIZA, NO SE CAMBIA: cuando el plan no está
     * vigente, elegir un plan no es un "cambio" sino la forma de regularizar la
     * cuenta, así que se aplica y el estado vuelve a activo. Bloquearlo acá dejaría
     * al contribuyente sin salida: en este corte no hay ninguna otra pantalla que
     * reactive el plan. El cambio sigue sin reiniciar el contador del mes.
     *
     * La respuesta lleva `requested` (lo que lee la pantalla) y además `status`, que
     * distingue el cambio aplicado del pedido registrado sin ambigüedad.
     */
    public function change(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'plan_code' => ['required', 'string', 'max:32'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        $companyId = $this->companyId();
        $user = $request->user();
        $estado = $this->overview->forCompany($companyId, $user);

        if (! $estado['can_manage']) {
            $pedido = $this->changer->request($companyId, $datos['plan_code'], $user?->id, $datos['reason'] ?? null);

            return response()->json([
                'data' => [
                    'requested' => true,
                    'status' => 'requested',
                    'plan' => [
                        'code' => $pedido->requestedPlan?->code,
                        'name' => $pedido->requestedPlan?->name,
                    ],
                ],
                'message' => 'El pedido quedó registrado: quien administra la empresa lo va a ver.',
            ], Response::HTTP_ACCEPTED);
        }

        $suscripcion = $this->changer->change(
            $companyId,
            $datos['plan_code'],
            $user?->id,
            PlanChangeLog::SOURCE_CUSTOMER,
            $datos['reason'] ?? null
        );

        // El contador NO se reinicia: se informa el plan nuevo con el consumo que ya
        // había, para que la pantalla pueda explicarlo sin parecer una falla.
        return response()->json([
            'data' => [
                'requested' => false,
                'status' => 'applied',
                'plan' => [
                    'id' => (int) $suscripcion->plan_id,
                    'code' => $suscripcion->plan?->code,
                    'name' => $suscripcion->plan?->name,
                ],
                'usage' => $estado['usage'],
                'period' => $estado['period'],
            ],
            'message' => 'Plan actualizado. El contador del mes sigue igual: no se reinicia al cambiar de plan.',
        ]);
    }

    /**
     * GET plan/history
     *
     * Quién cambió el plan, cuándo, de qué a qué y por qué.
     *
     * Viajan los CÓDIGOS y los NOMBRES además de los ids: la pantalla muestra un
     * historial legible y no tiene que cruzar ids contra el catálogo para poder
     * dibujarlo (y no se queda sin nombres si un plan se dio de baja).
     */
    public function history(): JsonResponse
    {
        $companyId = $this->companyId();

        $planes = $this->catalog->sellable()->keyBy('id');
        $interno = $this->catalog->internal();

        if ($interno !== null) {
            $planes->put($interno->id, $interno);
        }

        $nombreDe = static function (?int $planId) use ($planes): array {
            if ($planId === null) {
                return ['code' => null, 'name' => null];
            }

            $plan = $planes->get($planId);

            return ['code' => $plan?->code, 'name' => $plan?->name];
        };

        $filas = $this->changer->history($companyId)->map(static function (PlanChangeLog $fila) use ($nombreDe): array {
            $desde = $nombreDe($fila->from_plan_id === null ? null : (int) $fila->from_plan_id);
            $hasta = $nombreDe((int) $fila->to_plan_id);

            return [
                'from_code' => $desde['code'],
                'from_name' => $desde['name'],
                'to_code' => $hasta['code'],
                'to_name' => $hasta['name'],
                'reason' => $fila->reason,
                'requested_by' => $fila->source,
                'created_at' => $fila->created_at?->toIso8601String(),
                // Los ids quedan también: sirven para auditar sin depender del catálogo.
                'from_plan_id' => $fila->from_plan_id === null ? null : (int) $fila->from_plan_id,
                'to_plan_id' => (int) $fila->to_plan_id,
                'user_id' => $fila->user_id === null ? null : (int) $fila->user_id,
                'source' => $fila->source,
                'at' => $fila->created_at?->toIso8601String(),
            ];
        })->all();

        return response()->json(['data' => $filas]);
    }

    /**
     * La empresa activa del request, o 422 si no hay ninguna.
     *
     * Sin empresa no se puede saber de qué CUIT se habla, y contestar con un plan
     * inventado sería peor que no contestar.
     */
    private function companyId(): int
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'No hay empresa activa.');
        }

        return $companyId;
    }
}
