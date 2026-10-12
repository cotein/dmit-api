<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\Customer;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreItem;
use App\Models\MercadoLibreSyncLog;
use App\Models\Order;
use App\Models\Product;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\OrderService;
use Illuminate\Support\Carbon;

/**
 * Trae las ventas de MercadoLibre y las convierte en pedidos locales, listos
 * para preparar, entregar y facturar.
 *
 * El pedido local se crea con los campos de MercadoLibre que ya existían en la
 * tabla `orders` (`meli_id`, `is_meli_order`, `meli_data`, `created_on_meli`) y
 * con el comprador resuelto a un cliente del catálogo: si MercadoLibre publica
 * los datos de facturación se usan para vincular o crear el cliente, que es lo
 * que después permite emitir la factura con el CUIT correcto.
 *
 * Idempotente por `meli_id`: volver a correr la importación no duplica ventas.
 */
class MercadoLibreOrderService
{
    public function __construct(
        private MercadoLibreClient $client,
        private MercadoLibreOAuthService $oauth,
        private OrderService $orders,
    ) {
    }

    /**
     * Importa las ventas de los últimos N días (configurable).
     */
    public function import(MercadoLibreAccount $account, ?Carbon $desde = null): MercadoLibreSyncLog
    {
        $desde = $desde ?? now()->subDays((int) config('mercadolibre.sync.orders_days_back', 30));

        $log = MercadoLibreSyncLog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'mercadolibre_account_id' => $account->id,
            'type' => MercadoLibreSyncLog::TYPE_ORDERS,
            'status' => MercadoLibreSyncLog::STATUS_RUNNING,
            'started_at' => now(),
            'context' => ['desde' => $desde->toDateTimeString()],
        ]);

        if (empty($account->ml_user_id)) {
            $log->addError('La cuenta no tiene user_id de MercadoLibre: hay que volver a conectarla.');
            $log->failed++;

            return $log->close('No se pudieron importar ventas: cuenta sin user_id.');
        }

        try {
            $client = $this->client->withToken($this->oauth->freshToken($account));

            $offset = 0;
            $limite = (int) config('mercadolibre.sync.orders_page_size', 50);
            $totales = 0;

            do {
                $pagina = $client->searchOrderIds((int) $account->ml_user_id, [
                    'order.date_created.from' => $desde->copy()->startOfDay()->toIso8601String(),
                    'order.date_created.to' => now()->toIso8601String(),
                    'offset' => $offset,
                    'limit' => $limite,
                ]);

                foreach ($pagina['results'] as $orderId) {
                    $log->processed++;

                    try {
                        $orden = $client->order($orderId);

                        if ($this->importOrder($account, $orden) !== null) {
                            $log->created++;
                        } else {
                            $log->skipped++;
                        }
                    } catch (MercadoLibreException $e) {
                        $log->failed++;
                        $log->addError("No se pudo importar la venta {$orderId}: " . $e->userMessage());
                    }
                }

                $totales = $pagina['total'];
                $offset += $limite;
            } while ($offset < $totales && $pagina['results'] !== []);

            return $log->close("Se revisaron {$log->processed} ventas de MercadoLibre.");
        } catch (\Throwable $e) {
            $log->addError($e instanceof MercadoLibreException ? $e->userMessage() : $e->getMessage());
            $log->failed++;

            return $log->close('La importación de ventas se cortó por un error.');
        }
    }

    /**
     * Pide el detalle de una venta a la API. Lo usan el import y el webhook
     * (que sólo recibe el id en la notificación).
     */
    public function fetchOrder(MercadoLibreAccount $account, string $orderId): array
    {
        return $this->client
            ->withToken($this->oauth->freshToken($account))
            ->order($orderId);
    }

    /**
     * Convierte una orden de MercadoLibre en un pedido local.
     *
     * Devuelve null si esa venta ya estaba importada (o no tiene ítems).
     */
    public function importOrder(MercadoLibreAccount $account, array $ordenMl): ?Order
    {
        $meliId = (string) ($ordenMl['id'] ?? '');

        if ($meliId === '') {
            return null;
        }

        $yaExiste = Order::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $account->company_id)
            ->where('meli_id', $meliId)
            ->exists();

        if ($yaExiste) {
            return null;
        }

        $items = $this->armarItems($account, $ordenMl);

        if ($items === []) {
            return null;
        }

        $cliente = $this->resolverCliente($account, $ordenMl);

        // OJO: no se usa OrderService::createOrder() a propósito. Ese método
        // descarta el company_id y lo deja al trait, que en consola no tiene
        // empresa activa; el pedido quedaría sin empresa (columna NOT NULL).
        // Se crea acá con la empresa explícita y se recalcula el total con el
        // servicio local, para que la cuenta siga siendo la misma.
        $orden = Order::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'customer_id' => $cliente?->id,
            'user_id' => null,
            'code' => $meliId,
            'total' => 0,
            'pay_method' => 1,
            'date' => $ordenMl['date_created'] ?? null,
            'created_on_meli' => ! empty($ordenMl['date_created'])
                ? Carbon::parse($ordenMl['date_created'])
                : null,
            'delivery_date' => ! empty($ordenMl['date_closed']) ? Carbon::parse($ordenMl['date_closed']) : null,
            'meli_id' => $meliId,
            'is_meli_order' => true,
            'meli_data' => $ordenMl,
            'delivery_address' => $this->direccionDeEnvio($ordenMl),
            'is_editing' => false,
        ]);

        $orden->items()->createMany($items);
        $this->orders->recalculateOrderTotal($orden);

        return $orden->refresh();
    }

    /**
     * Traduce los ítems de la venta a ítems del pedido local.
     *
     * El producto local sale del vínculo que ya existe (publicación → producto).
     * Si la publicación no está vinculada, el ítem se carga igual sin producto:
     * la venta no se pierde, y en la conciliación se puede resolver después.
     *
     * @return array<int, array<string, mixed>>
     */
    private function armarItems(MercadoLibreAccount $account, array $ordenMl): array
    {
        $items = [];

        foreach (($ordenMl['order_items'] ?? []) as $itemMl) {
            $mlItemId = $itemMl['item']['id'] ?? null;
            $cantidad = (float) ($itemMl['quantity'] ?? 1);
            $unitario = (float) ($itemMl['unit_price'] ?? 0);

            $producto = null;

            if ($mlItemId !== null) {
                $item = MercadoLibreItem::withoutGlobalScope(CompanyScope::class)
                    ->where('company_id', $account->company_id)
                    ->where('item_id', $mlItemId)
                    ->first();

                if ($item !== null && $item->product_id !== null) {
                    $producto = Product::find($item->product_id);
                }
            }

            $ivaPorcentaje = $this->ivaDe($producto);

            // Mismo cálculo que OrderService::normalizeItems: base menos
            // descuento más IVA. Acá no hay descuento por ítem (MercadoLibre ya
            // descuenta el precio de lista).
            $neto = round($cantidad * $unitario, 2);
            $ivaImporte = round($neto * $ivaPorcentaje / 100, 2);

            $items[] = [
                'product_id' => $producto?->id,
                'iva_id' => $producto?->iva_id,
                'unit_price' => $unitario,
                'quantity' => $cantidad,
                'discount_percentage' => 0,
                'discount_import' => 0,
                'iva_percentage' => $ivaPorcentaje,
                'iva_import' => $ivaImporte,
                'neto_import' => $neto,
                'total' => round($neto + $ivaImporte, 2),
                'is_chp' => false,
            ];
        }

        return $items;
    }

    /** Porcentaje de IVA del producto, si el catálogo lo tiene cargado. */
    private function ivaDe(?Product $producto): float
    {
        if ($producto === null || $producto->iva_id === null) {
            return 0.0;
        }

        $porcentaje = $producto->iva?->percentage;

        return is_numeric($porcentaje) ? (float) $porcentaje : 0.0;
    }

    /**
     * Resuelve el comprador a un cliente local.
     *
     * Primero intenta con los datos fiscales que MercadoLibre publica para la
     * venta (es lo que permite facturar a nombre del comprador); si no están,
     * cae en un cliente genérico por venta, que el usuario completa después.
     */
    private function resolverCliente(MercadoLibreAccount $account, array $ordenMl): ?Customer
    {
        $comprador = $ordenMl['buyer'] ?? [];
        $nickname = $comprador['nickname'] ?? null;
        $numeroDocumento = null;
        $nombre = null;
        $apellido = null;

        $facturacion = $this->datosDeFacturacion($account, (string) ($ordenMl['id'] ?? ''));

        if (is_array($facturacion)) {
            $documento = $facturacion['buyer']['tax_id'] ?? null;
            $numeroDocumento = is_array($documento) ? ($documento['number'] ?? null) : null;
            $nombre = $facturacion['buyer']['name'] ?? null;
            $apellido = $facturacion['buyer']['last_name'] ?? null;
        }

        if (! empty($numeroDocumento)) {
            $existente = Customer::withoutGlobalScope(CompanyScope::class)
                ->where('company_id', $account->company_id)
                ->where('afip_number', (string) $numeroDocumento)
                ->first();

            if ($existente !== null) {
                return $existente;
            }
        }

        $nombreFinal = trim((string) ($nombre ?: $nickname ?: 'Comprador'));
        $etiqueta = 'ML ' . mb_substr(trim($nombreFinal . ' ' . (string) $apellido), 0, 180);

        $existente = Customer::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $account->company_id)
            ->where('name', mb_substr($etiqueta, 0, 191))
            ->first();

        if ($existente !== null) {
            return $existente;
        }

        return Customer::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'name' => mb_substr($etiqueta, 0, 191),
            'last_name' => $apellido !== null ? mb_substr((string) $apellido, 0, 191) : null,
            'fantasy_name' => $nickname !== null ? mb_substr((string) $nickname, 0, 191) : null,
            'afip_number' => $numeroDocumento !== null ? (string) $numeroDocumento : null,
            // Los datos crudos quedan guardados: el usuario completa la condición
            // frente al IVA desde la pantalla de clientes antes de facturar.
            'afip_data' => is_array($facturacion) ? $facturacion : null,
            'active' => 1,
        ]);
    }

    /** Datos fiscales que MercadoLibre publica para la venta (si el comprador los cargó). */
    private function datosDeFacturacion(MercadoLibreAccount $account, string $orderId): ?array
    {
        if ($orderId === '') {
            return null;
        }

        try {
            return $this->client
                ->withToken($this->oauth->freshToken($account))
                ->get("orders/{$orderId}/billing_info");
        } catch (\Throwable $e) {
            // Es opcional: sin datos fiscales la venta se importa igual.
            return null;
        }
    }

    private function direccionDeEnvio(array $ordenMl): ?string
    {
        $envio = $ordenMl['shipping']['id'] ?? null;
        $receptor = $ordenMl['shipping']['receiver_address'] ?? null;

        if (is_array($receptor)) {
            $partes = array_filter([
                $receptor['address_line'] ?? null,
                $receptor['street_name'] ?? null,
                $receptor['street_number'] ?? null,
                $receptor['city']['name'] ?? null,
                $receptor['state']['name'] ?? null,
                $receptor['zip_code'] ?? null,
            ]);

            if ($partes !== []) {
                return mb_substr(implode(', ', $partes), 0, 255);
            }
        }

        return $envio !== null ? ('Envío MercadoLibre #' . $envio) : null;
    }
}
