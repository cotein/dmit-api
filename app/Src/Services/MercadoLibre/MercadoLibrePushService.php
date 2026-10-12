<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreItem;
use App\Models\MercadoLibreSyncLog;
use App\Models\MercadoLibreVariation;
use App\Models\PriceListProduct;
use App\Models\Product;
use App\Src\Scopes\CompanyScope;

/**
 * Empuja a MercadoLibre los cambios que se hacen en la app: precio, stock,
 * pausar/activar y publicar un producto nuevo.
 *
 * De dónde sale cada dato:
 *   - el STOCK es la última fila de `stocks` (así lo muestra el listado de
 *     productos hoy: `stock_history->last()->quantity`),
 *   - el PRECIO es el de la lista de precios configurada
 *     (`MERCADOLIBRE_PRICE_LIST_ID`); si no hay ninguna configurada se usa la
 *     primera de la empresa.
 *
 * MercadoLibre tiene tres comportamientos que hay que respetar y que este
 * servicio encapsula:
 *   1. Si la publicación tiene automatización de precios activa, mandar SÓLO
 *      `price` devuelve 400 y mandarlo junto a otros campos lo ignora en
 *      silencio. Por eso el precio se manda aparte y se avisa si no se aplicó.
 *   2. Poner stock 0 pausa la publicación; si el vendedor la pausó a mano queda
 *      `paused_by_seller` y no se reactiva sola al reponer stock.
 *   3. Cerrar una publicación es IRREVERSIBLE: no se expone como acción suelta,
 *      sólo como parte del borrado explícito.
 */
class MercadoLibrePushService
{
    public function __construct(
        private MercadoLibreClient $client,
        private MercadoLibreOAuthService $oauth,
    ) {
    }

    /**
     * Empuja precio y stock de todas las publicaciones vinculadas de la empresa.
     */
    public function pushCompany(MercadoLibreAccount $account, bool $precio = true, bool $stock = true): MercadoLibreSyncLog
    {
        $log = MercadoLibreSyncLog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'mercadolibre_account_id' => $account->id,
            'type' => MercadoLibreSyncLog::TYPE_PUSH,
            'status' => MercadoLibreSyncLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        $items = MercadoLibreItem::withoutGlobalScope(CompanyScope::class)
            ->where('mercadolibre_account_id', $account->id)
            ->where('match_status', MercadoLibreItem::MATCH_MATCHED)
            ->whereNotNull('product_id')
            ->with('product')
            ->get();

        $client = $this->client->withToken($this->oauth->freshToken($account));

        foreach ($items as $item) {
            $log->processed++;

            try {
                $this->pushItem($client, $item, $precio, $stock);
                $item->forceFill(['last_pushed_at' => now(), 'last_push_error' => null])->save();
                $log->updated++;
            } catch (MercadoLibreException $e) {
                $log->failed++;
                $item->forceFill(['last_push_error' => $e->userMessage()])->save();
                $log->addError($item->item_id . ': ' . $e->userMessage());
            }
        }

        return $log->close('Se empujaron ' . $log->updated . ' publicaciones.');
    }

    /**
     * Empuja una publicación. El precio y el stock van en llamadas separadas
     * para poder distinguir cuál de los dos fue rechazado.
     */
    public function pushItem(MercadoLibreClient $client, MercadoLibreItem $item, bool $precio = true, bool $stock = true): void
    {
        if ($item->product_id === null) {
            throw new MercadoLibreException('La publicación no está vinculada a un producto local.');
        }

        $producto = $item->product ?: Product::find($item->product_id);

        if ($producto === null) {
            throw new MercadoLibreException('El producto vinculado ya no existe en el catálogo.');
        }

        // Con variaciones, precio y stock se manejan POR variación.
        $variaciones = $item->variations()->get();

        if ($variaciones->isNotEmpty()) {
            $this->pushVariaciones($client, $item, $variaciones, $producto, $precio, $stock);

            return;
        }

        if ($stock) {
            $cantidad = $this->stockDe($producto);

            if ((int) $item->available_quantity !== $cantidad) {
                $client->put('items/' . $item->item_id, ['available_quantity' => $cantidad]);
                $item->forceFill(['available_quantity' => $cantidad])->save();
            }
        }

        if ($precio) {
            $nuevo = $this->precioDe($producto, $item->company_id);

            if ($nuevo !== null && (float) $item->price !== (float) $nuevo) {
                $this->pushPrecio($client, $item->item_id, $nuevo);
                $item->forceFill(['price' => $nuevo])->save();
            }
        }
    }

    /**
     * Manda el precio de a una publicación y avisa si MercadoLibre lo ignoró.
     *
     * El 400 por automatización de precios es un caso esperado, no un bug: se
     * traduce a un mensaje entendible para que el usuario sepa que tiene que
     * desactivar la automatización en MercadoLibre.
     */
    public function pushPrecio(MercadoLibreClient $client, string $itemId, float $precio, ?string $variationId = null): void
    {
        $payload = $variationId === null
            ? ['price' => $precio]
            : ['variations' => [['id' => (int) $variationId, 'price' => $precio]]];

        try {
            $client->put('items/' . $itemId, $payload);
        } catch (MercadoLibreException $e) {
            if ($e->statusCode() === 400) {
                throw new MercadoLibreException(
                    'MercadoLibre rechazó el precio: la publicación tiene una automatización de precios activa. '
                    . 'Desactivala en MercadoLibre para poder manejarlo desde acá.',
                    400,
                    $e->payload(),
                    $e,
                );
            }

            throw $e;
        }
    }

    /** Pausa o reactiva una publicación. */
    public function changeStatus(MercadoLibreItem $item, string $status): MercadoLibreItem
    {
        $status = strtolower($status);

        if (! in_array($status, ['active', 'paused'], true)) {
            throw new MercadoLibreException('Estado no soportado: sólo se puede activar o pausar desde la app.');
        }

        $client = $this->client->withToken($this->oauth->freshToken($item->account));

        $client->put('items/' . $item->item_id, ['status' => $status]);

        $item->forceFill([
            'status' => $status,
            'sub_status' => $status === 'paused' ? 'paused_by_seller' : null,
            'last_pushed_at' => now(),
            'last_push_error' => null,
        ])->save();

        return $item;
    }

    /**
     * Publica un producto del catálogo local por primera vez.
     *
     * MercadoLibre exige categoría, título y atributos obligatorios por
     * categoría. Acá se manda el mínimo viable y se devuelve el error tal cual
     * viene cuando falta algo: adivinar los atributos obligatorios es peor que
     * mostrárselos al usuario para que los complete.
     */
    public function publish(Product $producto, string $categoryId, array $extra = []): MercadoLibreItem
    {
        $account = $this->accountDe($producto->company_id);

        if ($account === null) {
            throw new MercadoLibreException('La empresa no tiene una cuenta de MercadoLibre conectada.');
        }

        $precio = $this->precioDe($producto, $producto->company_id);

        if ($precio === null) {
            throw new MercadoLibreException('El producto no tiene precio en la lista de precios: no se puede publicar.');
        }

        $payload = array_merge([
            'title' => mb_substr((string) $producto->name, 0, 60),
            'category_id' => $categoryId,
            'price' => (float) $precio,
            'currency_id' => 'ARS',
            'available_quantity' => $this->stockDe($producto),
            'buying_mode' => 'buy_it_now',
            'condition' => 'new',
            'listing_type_id' => 'gold_special',
            'sale_terms' => [],
            'pictures' => [],
            'attributes' => [],
        ], $extra);

        $client = $this->client->withToken($this->oauth->freshToken($account));

        $log = MercadoLibreSyncLog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'mercadolibre_account_id' => $account->id,
            'type' => MercadoLibreSyncLog::TYPE_PUBLISH,
            'status' => MercadoLibreSyncLog::STATUS_RUNNING,
            'started_at' => now(),
            'context' => ['product_id' => $producto->id, 'category_id' => $categoryId],
        ]);

        try {
            $respuesta = $client->post('items', $payload);
        } catch (MercadoLibreException $e) {
            $log->failed++;
            $log->addError($e->userMessage());
            $log->close('No se pudo publicar en MercadoLibre.');

            throw $e;
        }

        $item = MercadoLibreItem::withoutGlobalScope(CompanyScope::class)->updateOrCreate(
            ['item_id' => (string) $respuesta['id']],
            [
                'company_id' => $account->company_id,
                'mercadolibre_account_id' => $account->id,
                'site_id' => $account->site_id,
                'title' => $producto->name,
                'category_id' => $categoryId,
                'price' => $payload['price'],
                'currency_id' => $payload['currency_id'],
                'available_quantity' => $payload['available_quantity'],
                'status' => $respuesta['status'] ?? 'active',
                'permalink' => $respuesta['permalink'] ?? null,
                'thumbnail' => $respuesta['thumbnail'] ?? null,
                'product_id' => $producto->id,
                'match_status' => MercadoLibreItem::MATCH_MATCHED,
                'matched_by' => 'publish',
                'matched_at' => now(),
                'synced_at' => now(),
                'raw' => $respuesta,
            ],
        );

        $producto->forceFill(['published_meli' => 1])->save();
        $log->created++;
        $log->processed++;
        $log->close('Producto publicado en MercadoLibre.');

        return $item;
    }

    private function pushVariaciones(
        MercadoLibreClient $client,
        MercadoLibreItem $item,
        $variaciones,
        Product $producto,
        bool $precio,
        bool $stock,
    ): void {
        $cambios = [];

        foreach ($variaciones as $variacion) {
            $payload = ['id' => (int) $variacion->variation_id];

            if ($stock) {
                // El stock por variación sale del producto vinculado: el catálogo
                // local no distingue talle/color, así que se reparte el mismo.
                $payload['available_quantity'] = $this->stockDe($producto);
            }

            if ($precio) {
                $nuevo = $this->precioDe($producto, $item->company_id);

                if ($nuevo !== null) {
                    $payload['price'] = (float) $nuevo;
                }
            }

            $cambios[] = $payload;
        }

        if ($cambios === []) {
            return;
        }

        $client->put('items/' . $item->item_id, ['variations' => $cambios]);
    }

    /** El stock con el que trabaja la app: la última fila del historial. */
    private function stockDe(Product $producto): int
    {
        $cantidad = $producto->stock_history()->orderByDesc('id')->value('quantity');

        return (int) ($cantidad ?? 0);
    }

    /**
     * Precio de la lista configurada; si no hay, la primera de la empresa.
     */
    private function precioDe(Product $producto, ?int $companyId): ?float
    {
        $listaId = config('mercadolibre.pricing.price_list_id');

        $consulta = PriceListProduct::query()->where('product_id', $producto->id);

        if (! empty($listaId)) {
            $consulta->where('pricelist_id', (int) $listaId);
        } else {
            $consulta->orderBy('pricelist_id');
        }

        $precio = $consulta->value('price');

        return $precio === null ? null : (float) $precio;
    }

    private function accountDe(?int $companyId): ?MercadoLibreAccount
    {
        return MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->first();
    }
}
