<?php

declare(strict_types=1);

namespace App\Exceptions\Plans;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El mismo comprobante ya se emitió: el pedido es un reintento, no uno nuevo.
 *
 * Pasa cuando el navegador no llegó a ver la respuesta de una emisión que sí se
 * autorizó y el usuario reintenta. Sin esto, el reintento consumiría otro lugar del
 * contador y podría emitir un segundo comprobante igual.
 *
 * Es un 409 y no un rechazo por tope: el problema no es el tope del mes, es que ese
 * comprobante ya existe. Si se contestara con el contrato del tope, el usuario
 * subiría de plan para arreglar algo que no se arregla subiendo de plan.
 */
final class PlanDuplicateEmissionException extends Exception
{
    public const CODE = 'PLAN_DUPLICATE_EMISSION';

    public function __construct(private readonly string $resource)
    {
        parent::__construct(
            'Este comprobante ya se emitió: el reintento no consume otro lugar del contador ni emite de nuevo.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'data' => [
                'resource' => $this->resource,
            ],
        ], 409);
    }
}
