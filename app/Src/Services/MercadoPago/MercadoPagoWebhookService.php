<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Entrada de las notificaciones de Mercado Pago.
 *
 * Hace tres cosas, en este orden:
 *  1. valida la firma (si no cierra, se guarda igual pero marcada y NO se procesa),
 *  2. guarda el evento con `mp_event_id` único → los reintentos de MP no se repiten,
 *  3. encola el procesamiento y contesta 200 enseguida: MP reintenta si tardamos.
 */
class MercadoPagoWebhookService
{
    public function __construct(private MercadoPagoSignatureValidator $validator)
    {
    }

    /**
     * @return array{status: string, event: ?MercadoPagoWebhookEvent}
     */
    public function handle(Request $request): array
    {
        $payload = $request->all();
        $topic = $payload['type'] ?? $payload['topic'] ?? $request->query('type') ?? $request->query('topic');
        $accion = $payload['action'] ?? null;

        // El id del recurso viaja en el cuerpo (data.id) o en el query string.
        $dataId = $payload['data']['id'] ?? $request->query('data.id') ?? $request->query('id');

        // Mercado Pago reintenta el MISMO evento (y a veces lo manda duplicado en
        // paralelo). Si ya lo tenemos, se contesta 200 sin volver a procesarlo.
        $eventId = $payload['id'] ?? $request->query('id') ?? null;

        if (! empty($eventId)) {
            $existente = MercadoPagoWebhookEvent::where('mp_event_id', $eventId)->first();

            if ($existente) {
                return ['status' => 'duplicate', 'event' => $existente];
            }
        }

        $firmaValida = $this->validator->isValid(
            $request->header('x-signature'),
            $request->header('x-request-id'),
            $dataId !== null ? (string) $dataId : null,
        );

        try {
            $evento = MercadoPagoWebhookEvent::create([
                'mp_event_id' => $eventId,
                'topic' => $topic,
                'action' => $accion,
                'resource_id' => $dataId !== null ? (string) $dataId : null,
                'signature_valid' => $firmaValida,
                'payload' => $payload + ['_query' => $request->query()],
                'status' => $firmaValida ? MercadoPagoWebhookEvent::STATUS_RECEIVED : MercadoPagoWebhookEvent::STATUS_IGNORED,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Carrera: dos entregas del mismo evento al mismo tiempo. La primera
            // gana y esta se descarta.
            $existente = MercadoPagoWebhookEvent::where('mp_event_id', $eventId)->first();

            return ['status' => 'duplicate', 'event' => $existente];
        }

        if (! $firmaValida) {
            Log::warning('Mercado Pago: notificación con firma inválida', [
                'event_id' => $evento->id,
                'topic' => $topic,
                'resource_id' => $dataId,
            ]);

            return ['status' => 'invalid_signature', 'event' => $evento];
        }

        // Se encola por closure y no por una clase en app/Jobs: ese directorio está
        // owned by root. Con QUEUE_CONNECTION=sync corre inline igual; el día que
        // haya worker, pasa a segundo plano sin tocar nada más.
        dispatch(function () use ($evento) {
            app(MercadoPagoEventProcessor::class)->process($evento->id);
        });

        return ['status' => 'accepted', 'event' => $evento];
    }
}
