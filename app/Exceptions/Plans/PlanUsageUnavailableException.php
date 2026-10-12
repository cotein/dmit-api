<?php

declare(strict_types=1);

namespace App\Exceptions\Plans;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * No se pudo registrar el consumo: la emisión FALLA.
 *
 * La regla es del negocio y no admite atajos: si el consumo no se puede escribir,
 * se emite sin contar y el contador pasa a ser decorativo. Así que acá se devuelve
 * un error y NO un comprobante.
 *
 * Es un 503 y no un 500 a propósito: el problema es pasajero (la base no respondió)
 * y el camino correcto es reintentar, no que el usuario llame a soporte.
 */
final class PlanUsageUnavailableException extends Exception
{
    public const CODE = 'PLAN_USAGE_UNAVAILABLE';

    public function __construct(private readonly string $resource, ?string $detalle = null)
    {
        $mensaje = 'No se pudo registrar el consumo del plan, así que el comprobante NO se emitió. Reintentá en unos segundos.';

        if ($detalle !== null) {
            $mensaje .= ' (' . $detalle . ')';
        }

        parent::__construct($mensaje);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'data' => [
                'resource' => $this->resource,
            ],
        ], 503);
    }
}
