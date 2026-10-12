<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreWebhookEvent;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;

/**
 * Notificaciones de MercadoLibre.
 *
 * El webhook sólo AVISA: se guarda el payload y se responde 200 enseguida
 * (MercadoLibre corta a los 500 ms y reintenta hasta 8 veces en una hora; si
 * igual se le escapa, el tópico puede quedar desactivado). El trabajo real lo
 * hace `processPending()`, que corre en el comando de sincronización.
 *
 * Nunca se confía en el contenido de la notificación: el dato se vuelve a pedir
 * a la API con el token de la empresa. Así una notificación falsa no puede
 * ensuciar el espejo local.
 */
class MercadoLibreWebhookService
{
    /** Tópicos que sabemos procesar. El resto se guarda y se marca ignorado. */
    private const TOPICS_SOPORTADOS = ['items', 'orders_v2', 'orders'];

    public function __construct(
        private MercadoLibreItemSyncService $items,
        private MercadoLibreOrderService $orders,
    ) {
    }

    /**
     * Guarda la notificación tal como llegó. No procesa nada: el controlador
     * tiene que responder rápido.
     */
    public function receive(array $payload): MercadoLibreWebhookEvent
    {
        $mlUserId = $payload['user_id'] ?? null;

        $cuenta = $mlUserId !== null
            ? MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
                ->where('ml_user_id', (int) $mlUserId)
                ->first()
            : null;

        return MercadoLibreWebhookEvent::withoutGlobalScope(CompanyScope::class)->firstOrCreate(
            ['notification_id' => (string) ($payload['_id'] ?? '')],
            [
                'company_id' => $cuenta?->company_id,
                'mercadolibre_account_id' => $cuenta?->id,
                'topic' => $payload['topic'] ?? null,
                'ml_user_id' => $mlUserId !== null ? (int) $mlUserId : null,
                'resource' => mb_substr((string) ($payload['resource'] ?? ''), 0, 255),
                'site_id' => $payload['site_id'] ?? null,
                'attempts' => isset($payload['attempts']) ? (int) $payload['attempts'] : null,
                'sent_at' => ! empty($payload['sent']) ? \Carbon\Carbon::parse($payload['sent']) : null,
                'status' => MercadoLibreWebhookEvent::STATUS_PENDING,
                'payload' => $payload,
            ],
        );
    }

    /**
     * Procesa las notificaciones pendientes.
     *
     * @return array{processed: int, failed: int, ignored: int}
     */
    public function processPending(int $limite = 100): array
    {
        $eventos = MercadoLibreWebhookEvent::withoutGlobalScope(CompanyScope::class)
            ->where('status', MercadoLibreWebhookEvent::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limite)
            ->get();

        $contadores = ['processed' => 0, 'failed' => 0, 'ignored' => 0];

        foreach ($eventos as $evento) {
            try {
                if ($this->process($evento)) {
                    $contadores['processed']++;
                } else {
                    $contadores['ignored']++;
                }
            } catch (\Throwable $e) {
                $contadores['failed']++;
                $evento->update([
                    'status' => MercadoLibreWebhookEvent::STATUS_FAILED,
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ]);

                Log::warning('MercadoLibre: no se pudo procesar una notificación', [
                    'topic' => $evento->topic,
                    'resource' => $evento->resource,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $contadores;
    }

    /**
     * Procesa una notificación. Devuelve true si se aplicó, false si se ignoró
     * (tópico no soportado, sin cuenta asociada o sin recurso reconocible).
     */
    public function process(MercadoLibreWebhookEvent $evento): bool
    {
        $cuenta = $evento->mercadolibre_account_id !== null
            ? MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)->find($evento->mercadolibre_account_id)
            : null;

        if ($cuenta === null) {
            $evento->update([
                'status' => MercadoLibreWebhookEvent::STATUS_IGNORED,
                'error' => 'No hay una cuenta conectada para ese user_id.',
            ]);

            return false;
        }

        if (! in_array((string) $evento->topic, self::TOPICS_SOPORTADOS, true)) {
            $evento->update([
                'status' => MercadoLibreWebhookEvent::STATUS_IGNORED,
                'error' => 'Tópico no soportado todavía: ' . $evento->topic,
            ]);

            return false;
        }

        $itemId = $this->idDeRecurso((string) $evento->resource, 'items');
        $orderId = $this->idDeRecurso((string) $evento->resource, 'orders');

        if ($itemId !== null) {
            $this->items->syncItemId($cuenta, $itemId);
        } elseif ($orderId !== null) {
            // La notificación sólo trae el id: el detalle se pide a la API.
            $this->orders->importOrder($cuenta, $this->orders->fetchOrder($cuenta, $orderId));
        } else {
            $evento->update([
                'status' => MercadoLibreWebhookEvent::STATUS_IGNORED,
                'error' => 'No se pudo reconocer el recurso de la notificación.',
            ]);

            return false;
        }

        $evento->update([
            'status' => MercadoLibreWebhookEvent::STATUS_PROCESSED,
            'processed_at_ms' => (int) (microtime(true) * 1000),
            'error' => null,
        ]);

        return true;
    }

    /**
     * Saca el id del recurso ("/items/MLA123" → "MLA123").
     */
    private function idDeRecurso(string $resource, string $prefijo): ?string
    {
        if ($resource === '') {
            return null;
        }

        $ruta = parse_url($resource, PHP_URL_PATH) ?: $resource;
        $partes = array_values(array_filter(explode('/', $ruta)));

        if (count($partes) >= 2 && $partes[0] === $prefijo) {
            return (string) $partes[1];
        }

        return null;
    }
}
