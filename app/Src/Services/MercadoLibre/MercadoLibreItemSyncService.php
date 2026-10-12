<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreItem;
use App\Models\MercadoLibreSyncLog;
use App\Models\MercadoLibreVariation;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Log;

/**
 * Trae las publicaciones de MercadoLibre y las deja en el espejo local.
 *
 * El listado de MercadoLibre devuelve SÓLO ids (`users/{id}/items/search`), así
 * que el trabajo es en dos tiempos: primero se juntan los ids (con scan/scroll
 * cuando el catálogo es grande) y después se pide el detalle de a 20.
 *
 * Todo es idempotente: se puede volver a correr sin duplicar nada, porque el
 * upsert va por `item_id`. Eso importa porque el scan se reinicia solo cuando
 * el scroll vence a los 5 minutos.
 *
 * OJO con el scope por empresa: estos servicios también corren por consola, sin
 * empresa activa, así que todas las consultas van sin el scope global y con el
 * company_id explícito. Si no, el scope "fail-closed" devolvería cero filas y
 * crearíamos publicaciones duplicadas.
 */
class MercadoLibreItemSyncService
{
    public function __construct(
        private MercadoLibreClient $client,
        private MercadoLibreOAuthService $oauth,
        private MercadoLibreMatchService $matcher,
    ) {
    }

    /**
     * Importa (o actualiza) las publicaciones del vendedor.
     *
     * @param  array{status?: string}  $filtros  opcional: 'active' para traer sólo las activas
     */
    public function import(MercadoLibreAccount $account, array $filtros = []): MercadoLibreSyncLog
    {
        $log = MercadoLibreSyncLog::withoutGlobalScope(CompanyScope::class)->create([
            'company_id' => $account->company_id,
            'mercadolibre_account_id' => $account->id,
            'type' => MercadoLibreSyncLog::TYPE_IMPORT,
            'status' => MercadoLibreSyncLog::STATUS_RUNNING,
            'started_at' => now(),
            'context' => $filtros,
        ]);

        if (empty($account->ml_user_id)) {
            $log->addError('La cuenta no tiene user_id de MercadoLibre: hay que volver a conectarla.');
            $log->failed = 1;

            return $log->close('No se pudo importar: cuenta sin user_id.');
        }

        try {
            $token = $this->oauth->freshToken($account);
            $client = $this->client->withToken($token);

            $pendientes = [];
            $totalIds = 0;

            foreach ($this->client->withToken($token)->scanItemIds((int) $account->ml_user_id, $filtros) as $id) {
                $totalIds++;
                $pendientes[] = $id;

                // Se pide el detalle de a lotes para no juntar miles de ids en memoria.
                if (count($pendientes) >= (int) config('mercadolibre.sync.bulk_chunk', 20)) {
                    $this->procesarLote($client, $account, $pendientes, $log);
                    $pendientes = [];
                }
            }

            if ($pendientes !== []) {
                $this->procesarLote($client, $account, $pendientes, $log);
            }

            $log->processed = $totalIds;
            $account->forceFill(['last_synced_at' => now()])->save();

            return $log->close("Se leyeron {$totalIds} publicaciones de MercadoLibre.");
        } catch (\Throwable $e) {
            Log::error('MercadoLibre: falló la importación de publicaciones', [
                'company_id' => $account->company_id,
                'error' => $e->getMessage(),
            ]);

            $log->addError($e instanceof MercadoLibreException ? $e->userMessage() : $e->getMessage());
            $log->failed++;

            return $log->close('La importación se cortó por un error.');
        }
    }

    /**
     * Trae (o refresca) una publicación puntual. Es lo que usa el webhook del
     * tópico `items`: el aviso no se cree, el dato se pide a la API.
     */
    public function syncItemId(MercadoLibreAccount $account, string $itemId): ?MercadoLibreItem
    {
        $token = $this->oauth->freshToken($account);
        $client = $this->client->withToken($token);

        $detalle = $client->item($itemId);

        if (empty($detalle['id'])) {
            return null;
        }

        // Si la publicación se cerró y borró, el detalle viene sin título útil:
        // igual se guarda, porque el estado es justamente lo que cambió.
        return $this->upsert($account, $detalle);
    }

    /**
     * @param  string[]  $ids
     */
    private function procesarLote(MercadoLibreClient $client, MercadoLibreAccount $account, array $ids, MercadoLibreSyncLog $log): void
    {
        try {
            $detalles = $client->bulkItems($ids);
        } catch (MercadoLibreException $e) {
            $log->failed += count($ids);
            $log->addError('No se pudo leer el detalle de ' . count($ids) . ' publicaciones: ' . $e->userMessage());

            return;
        }

        foreach ($ids as $id) {
            $detalle = $detalles[$id] ?? null;

            if ($detalle === null) {
                $log->failed++;
                $log->addError("MercadoLibre no devolvió el detalle de {$id}.");

                continue;
            }

            try {
                $this->upsert($account, $detalle);
            } catch (\Throwable $e) {
                $log->failed++;
                $log->addError("No se pudo guardar {$id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Guarda (o actualiza) una publicación y sus variaciones.
     */
    public function upsert(MercadoLibreAccount $account, array $detalle): MercadoLibreItem
    {
        $itemId = (string) $detalle['id'];

        // Sin el scope global: en consola no hay empresa activa y el scope
        // devolvería null, creando una publicación duplicada.
        $item = MercadoLibreItem::withoutGlobalScope(CompanyScope::class)
            ->where('item_id', $itemId)
            ->first();

        if ($item === null) {
            $item = new MercadoLibreItem([
                'item_id' => $itemId,
                'company_id' => $account->company_id,
            ]);
        }

        $variaciones = is_array($detalle['variations'] ?? null) ? $detalle['variations'] : [];
        $sku = $this->matcher->skuFromItem($detalle, $variaciones);

        $item->fill([
            'company_id' => $account->company_id,
            'mercadolibre_account_id' => $account->id,
            'site_id' => $detalle['site_id'] ?? $account->site_id,
            'title' => $this->limpiar($detalle['title'] ?? null, 191),
            'category_id' => $detalle['category_id'] ?? null,
            'domain_id' => $detalle['domain_id'] ?? null,
            'listing_type_id' => $detalle['listing_type_id'] ?? null,
            'condition' => $detalle['condition'] ?? ($detalle['item_condition'] ?? null),
            'currency_id' => $detalle['currency_id'] ?? null,
            'price' => $detalle['price'] ?? null,
            'base_price' => $detalle['base_price'] ?? null,
            'available_quantity' => $detalle['available_quantity'] ?? null,
            'sold_quantity' => $detalle['sold_quantity'] ?? null,
            'status' => $detalle['status'] ?? null,
            'sub_status' => is_array($detalle['sub_status'] ?? null)
                ? implode(',', $detalle['sub_status'])
                : ($detalle['sub_status'] ?? null),
            'permalink' => $this->limpiar($detalle['permalink'] ?? null, 255),
            'thumbnail' => $detalle['thumbnail'] ?? null,
            'catalog_listing' => (bool) ($detalle['catalog_listing'] ?? false),
            'catalog_product_id' => $detalle['catalog_product_id'] ?? null,
            'family_name' => $this->limpiar($detalle['family_name'] ?? null, 191),
            'user_product_id' => $detalle['user_product_id'] ?? null,
            'health' => is_array($detalle['health'] ?? null) ? ($detalle['health']['status'] ?? null) : ($detalle['health'] ?? null),
            'tags' => is_array($detalle['tags'] ?? null) ? $detalle['tags'] : [],
            'ml_last_updated' => ! empty($detalle['last_updated']) ? \Carbon\Carbon::parse($detalle['last_updated']) : null,
            'synced_at' => now(),
            'raw' => $detalle,
        ]);

        // El SKU no es columna del item: se guarda para mostrarlo en pantalla
        // dentro del JSON crudo y se usa para vincular.
        $item->save();

        $this->guardarVariaciones($item, $variaciones);

        // Un item ya vinculado no se re-vincula: si el usuario lo desvinculó a
        // mano, la próxima importación no se lo vuelve a pegar.
        if ($item->match_status !== MercadoLibreItem::MATCH_MATCHED || $item->product_id === null) {
            $this->matcher->autoMatch($item, $sku);
        }

        return $item->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $variaciones
     */
    private function guardarVariaciones(MercadoLibreItem $item, array $variaciones): void
    {
        $vistos = [];

        foreach ($variaciones as $variacion) {
            if (! isset($variacion['id'])) {
                continue;
            }

            $variationId = (string) $variacion['id'];
            $vistos[] = $variationId;

            $sku = null;

            foreach (($variacion['attributes'] ?? []) as $atributo) {
                if (($atributo['id'] ?? null) === 'SELLER_SKU') {
                    $sku = is_string($atributo['value_name'] ?? null) ? $atributo['value_name'] : null;
                }
            }

            MercadoLibreVariation::withoutGlobalScope(CompanyScope::class)
                ->updateOrCreate(
                    [
                        'mercadolibre_item_id' => $item->id,
                        'variation_id' => $variationId,
                    ],
                    [
                        'company_id' => $item->company_id,
                        'price' => $variacion['price'] ?? null,
                        'available_quantity' => $variacion['available_quantity'] ?? null,
                        'sold_quantity' => $variacion['sold_quantity'] ?? null,
                        'seller_sku' => $sku ?? ($variacion['seller_custom_field'] ?? null),
                        'catalog_product_id' => $variacion['catalog_product_id'] ?? null,
                        'attribute_combinations' => $variacion['attribute_combinations'] ?? [],
                        'attributes' => $variacion['attributes'] ?? [],
                        'picture_ids' => $variacion['picture_ids'] ?? [],
                        'ml_last_updated' => ! empty($variacion['last_updated'])
                            ? \Carbon\Carbon::parse($variacion['last_updated'])
                            : null,
                    ],
                );
        }

        // Las variaciones que ya no existen en MercadoLibre se borran del espejo.
        $borrar = MercadoLibreVariation::withoutGlobalScope(CompanyScope::class)
            ->where('mercadolibre_item_id', $item->id);

        if ($vistos !== []) {
            $borrar->whereNotIn('variation_id', $vistos);
        }

        $borrar->delete();
    }

    private function limpiar(?string $valor, int $largo): ?string
    {
        if ($valor === null) {
            return null;
        }

        return mb_substr($valor, 0, $largo);
    }
}
