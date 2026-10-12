<?php

declare(strict_types=1);

namespace App\Exceptions\Plans;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El contribuyente agotó el tope de su plan y no puede emitir más este mes.
 *
 * Lleva TODA la información del rechazo —recurso, usados, tope, periodo, reinicio,
 * plan actual y plan sugerido— porque la pantalla se corrige con estos datos y no
 * recalcula nada por su cuenta: si el aviso tuviera que adivinar, un día mostraría
 * un número distinto del que aplica el servidor.
 *
 * `code` = PLAN_LIMIT_REACHED es parte del contrato con el frontend: cambiarlo deja
 * la pantalla sin entender el rechazo y el usuario sin ver por qué no puede emitir.
 *
 * Dos cosas viajan APARTE de este rechazo, a propósito: el motivo de degradación del
 * plan (vencido o suspendido) y el error de ARCA. Son tres problemas distintos y no
 * comparten cartel.
 */
final class PlanLimitReachedException extends Exception
{
    public const CODE = 'PLAN_LIMIT_REACHED';

    /**
     * @param  array{from: string, to: string, renews_at: string}  $period
     * @param  array{code: string, name: string}  $currentPlan
     * @param  array{code: string, name: string, limit: int}|null  $suggestedPlan
     */
    public function __construct(
        private readonly string $resource,
        private readonly int $used,
        private readonly int $limit,
        private readonly array $period,
        private readonly array $currentPlan,
        private readonly ?array $suggestedPlan,
    ) {
        parent::__construct($this->armarMensaje());
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'data' => [
                'resource' => $this->resource,
                'used' => $this->used,
                'limit' => $this->limit,
                'period' => $this->period,
                'current_plan' => $this->currentPlan,
                'suggested_plan' => $this->suggestedPlan,
            ],
        ], 403);
    }

    private function armarMensaje(): string
    {
        $documento = $this->resource === 'remitos' ? 'remitos' : 'comprobantes';
        $plan = $this->currentPlan['name'] ?? 'actual';

        return sprintf(
            'Llegaste al tope de %d %s por mes del plan %s. El contador se renueva el %s.',
            $this->limit,
            $documento,
            $plan,
            $this->period['renews_at'] ?? 'mes siguiente'
        );
    }
}
