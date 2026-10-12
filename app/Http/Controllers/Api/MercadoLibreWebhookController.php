<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Services\MercadoLibre\MercadoLibreWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receptor de notificaciones de MercadoLibre.
 *
 * MercadoLibre espera un 200 en menos de 500 ms: si tardamos, reintenta hasta 8
 * veces en una hora y después descarta la notificación (y puede desactivarnos el
 * tópico). Por eso acá NO se procesa nada: se guarda el aviso y se responde. El
 * procesamiento lo hace el comando de sincronización.
 *
 * La notificación no se cree: es un aviso. El dato se pide después a la API con
 * el token de la empresa, así un POST falsificado no ensucia el espejo local.
 */
class MercadoLibreWebhookController extends Controller
{
    public function __construct(private MercadoLibreWebhookService $webhooks)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->all();

        try {
            $this->webhooks->receive($payload);
        } catch (\Throwable $e) {
            // Igual se responde 200: un error nuestro no tiene que hacer que
            // MercadoLibre reintente y termine apagando el tópico. El problema
            // queda en el log.
            Log::error('MercadoLibre: no se pudo guardar una notificación', [
                'error' => $e->getMessage(),
                'topic' => $payload['topic'] ?? null,
            ]);
        }

        return response()->json(['received' => true]);
    }
}
