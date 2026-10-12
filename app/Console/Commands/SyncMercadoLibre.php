<?php

namespace App\Console\Commands;

use App\Models\MercadoLibreAccount;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoLibre\MercadoLibreItemSyncService;
use App\Src\Services\MercadoLibre\MercadoLibreOrderService;
use App\Src\Services\MercadoLibre\MercadoLibrePushService;
use App\Src\Services\MercadoLibre\MercadoLibreWebhookService;
use Illuminate\Console\Command;

/**
 * Sincronización programada con MercadoLibre para todas las empresas conectadas.
 *
 * Hace cuatro cosas, en este orden:
 *   1. procesa las notificaciones que llegaron por webhook,
 *   2. importa las publicaciones (altas y cambios),
 *   3. importa las ventas nuevas como pedidos locales,
 *   4. empuja precio y stock de las publicaciones vinculadas.
 *
 * Toda la lógica vive en los servicios, que son los mismos que usa la app desde
 * los endpoints: si el servidor no tiene cron, el usuario puede forzar la
 * sincronización desde la pantalla y el resultado es idéntico.
 *
 * Requiere el scheduler corriendo (`php artisan schedule:run` desde cron).
 */
class SyncMercadoLibre extends Command
{
    protected $signature = 'mercadolibre:sincronizar
        {--company= : Sincronizar sólo esta empresa}
        {--tipo= : Qué correr: webhooks, import, push y/o orders (separados por coma)}
        {--sin-push : No empujar precio ni stock}
        {--limite= : Cuántas notificaciones pendientes procesar}';

    protected $description = 'Sincroniza publicaciones, ventas y precios/stock con MercadoLibre';

    public function handle(
        MercadoLibreWebhookService $webhooks,
        MercadoLibreItemSyncService $items,
        MercadoLibreOrderService $orders,
        MercadoLibrePushService $push,
    ): int {
        $tipos = $this->tipos();
        $companyId = $this->option('company') !== null ? (int) $this->option('company') : null;

        if (in_array('webhooks', $tipos, true)) {
            $contadores = $webhooks->processPending((int) ($this->option('limite') ?: 100));
            $this->info("Webhooks: {$contadores['processed']} procesados · {$contadores['ignored']} ignorados · {$contadores['failed']} con error");
        }

        $cuentas = MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
            ->where('status', MercadoLibreAccount::STATUS_CONNECTED)
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->get();

        if ($cuentas->isEmpty()) {
            $this->warn('No hay cuentas de MercadoLibre conectadas.');

            return self::SUCCESS;
        }

        foreach ($cuentas as $cuenta) {
            $this->line("Empresa {$cuenta->company_id} · cuenta {$cuenta->nickname} ({$cuenta->site_id})");

            if (in_array('import', $tipos, true)) {
                $log = $items->import($cuenta);
                $this->info('  Publicaciones: ' . $log->message);
            }

            if (in_array('orders', $tipos, true)) {
                $log = $orders->import($cuenta);
                $this->info('  Ventas: ' . $log->message);
            }

            if (in_array('push', $tipos, true) && ! $this->option('sin-push')) {
                $log = $push->pushCompany($cuenta);
                $this->info('  Precio y stock: ' . $log->message);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return string[]
     */
    private function tipos(): array
    {
        $valor = $this->option('tipo');

        if ($valor === null || $valor === '') {
            return ['webhooks', 'import', 'orders', 'push'];
        }

        $tipos = array_map('trim', explode(',', (string) $valor));

        foreach ($tipos as $tipo) {
            if (! in_array($tipo, ['webhooks', 'import', 'orders', 'push'], true)) {
                $this->warn("Tipo desconocido: {$tipo} (se ignora)");
            }
        }

        return $tipos;
    }
}
