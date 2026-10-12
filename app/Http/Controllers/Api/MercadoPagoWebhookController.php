<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Services\MercadoPago\MercadoPagoWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receptor de notificaciones de Mercado Pago. RUTA PÚBLICA (sin auth:api):
 * la llama Mercado Pago, no un usuario.
 *
 * Contesta 200 casi siempre a propósito: si devolvemos error, MP reintenta y no
 * gana nada (el evento ya quedó guardado). Lo único que rechaza de plano es una
 * firma inválida, que se registra y no se procesa.
 */
class MercadoPagoWebhookController extends Controller
{
    public function __construct(private MercadoPagoWebhookService $service)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $resultado = $this->service->handle($request);
        } catch (\Throwable $e) {
            Log::error('Mercado Pago: el webhook falló antes de guardarse', ['error' => $e->getMessage()]);

            // 200 igual: el problema es nuestro y reintentar no lo arregla.
            return response()->json(['status' => 'error']);
        }

        return response()->json([
            'status' => $resultado['status'],
            'event_id' => $resultado['event']?->id,
        ]);
    }
}
