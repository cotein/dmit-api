<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MercadoLibreAccount;
use App\Models\MercadoLibreItem;
use App\Models\MercadoLibreSyncLog;
use App\Models\Product;
use App\Src\Scopes\CompanyScope;
use App\Src\Services\MercadoLibre\MercadoLibreClient;
use App\Src\Services\MercadoLibre\MercadoLibreItemSyncService;
use App\Src\Services\MercadoLibre\MercadoLibreMatchService;
use App\Src\Services\MercadoLibre\MercadoLibreOAuthService;
use App\Src\Services\MercadoLibre\MercadoLibreOrderService;
use App\Src\Services\MercadoLibre\MercadoLibrePushService;
use App\Src\Support\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API del módulo de MercadoLibre para la app (todo autenticado).
 *
 * El webhook de MercadoLibre NO vive acá: está en MercadoLibreWebhookController,
 * porque lo llama MercadoLibre y no puede llevar auth:api. El callback de OAuth
 * también está aparte, porque lo llama el navegador volviendo de MercadoLibre.
 *
 * Los binds {item} y {product} usan el global scope de empresa: un usuario no
 * puede tocar publicaciones ni productos de otra empresa ni conociendo el id.
 */
class MercadoLibreController extends Controller
{
    public function __construct(
        private MercadoLibreOAuthService $oauth,
        private MercadoLibreItemSyncService $itemSync,
        private MercadoLibreMatchService $matcher,
        private MercadoLibrePushService $push,
        private MercadoLibreOrderService $orders,
        private MercadoLibreClient $client,
        private CurrentCompany $currentCompany,
    ) {
    }

    /**
     * Estado de la conexión: la cuenta, qué se sincronizó por última vez y los
     * números que se muestran en la pantalla de configuración.
     */
    public function account(): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return $this->sinEmpresa();
        }

        $cuenta = $this->cuentaDe($companyId);

        if ($cuenta === null) {
            return response()->json([
                'data' => [
                    'connected' => false,
                    'account' => null,
                    'stats' => $this->estadisticas($companyId),
                    'last_sync' => null,
                ],
                'message' => 'Todavía no conectaste tu cuenta de MercadoLibre.',
            ]);
        }

        $ultimo = MercadoLibreSyncLog::where('company_id', $companyId)->orderByDesc('id')->first();

        return response()->json([
            'data' => [
                'connected' => $cuenta->isConnected(),
                'account' => [
                    'ml_user_id' => $cuenta->ml_user_id,
                    'site_id' => $cuenta->site_id,
                    'nickname' => $cuenta->nickname,
                    'email' => $cuenta->email,
                    'status' => $cuenta->status,
                    'connected_at' => $cuenta->connected_at,
                    'last_refreshed_at' => $cuenta->last_refreshed_at,
                    'last_synced_at' => $cuenta->last_synced_at,
                    'last_error' => $cuenta->last_error,
                    // Importa para el alta de publicaciones: con este tag,
                    // MercadoLibre ya no acepta el modelo viejo con variaciones.
                    'user_product_seller' => $cuenta->user_product_seller,
                ],
                'stats' => $this->estadisticas($companyId),
                'last_sync' => $ultimo === null ? null : $this->logPayload($ultimo),
            ],
            'message' => $cuenta->isConnected() ? 'Cuenta conectada.' : 'La cuenta necesita volver a conectarse.',
        ]);
    }

    /**
     * Devuelve la URL de autorización. El navegador del usuario va a MercadoLibre
     * y vuelve al callback, que es una carga completa de página (no un XHR).
     */
    public function connect(): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return $this->sinEmpresa();
        }

        try {
            $datos = $this->oauth->authorizationUrl($companyId, (int) auth()->id());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'data' => ['url' => $datos['url']],
            'message' => 'Autorizá la conexión en MercadoLibre.',
        ]);
    }

    /** Desconecta la cuenta (y le avisa a MercadoLibre que ya no la usamos). */
    public function disconnect(): JsonResponse
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return $this->sinEmpresa();
        }

        $cuenta = $this->cuentaDe($companyId);

        if ($cuenta === null) {
            return response()->json(['message' => 'No hay una cuenta de MercadoLibre conectada.'], Response::HTTP_NOT_FOUND);
        }

        $this->oauth->disconnect($cuenta);

        return response()->json([
            'data' => ['connected' => false],
            'message' => 'Cuenta desconectada. Las publicaciones importadas se conservan.',
        ]);
    }

    /**
     * Listado de publicaciones importadas, con los filtros que usa la pantalla.
     */
    public function items(Request $request): JsonResponse
    {
        $consulta = MercadoLibreItem::query()
            ->with(['product:id,name,code'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('match_status'), fn ($q) => $q->where('match_status', $request->string('match_status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $texto = '%' . $request->string('search') . '%';

                $q->where(function ($sub) use ($texto) {
                    $sub->where('title', 'like', $texto)
                        ->orWhere('item_id', 'like', $texto);
                });
            })
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')));

        $total = (clone $consulta)->count();

        $items = $consulta
            ->orderByDesc('ml_last_updated')
            ->orderByDesc('id')
            ->forPage(max(1, $request->integer('page', 1)), min(100, max(1, $request->integer('per_page', 25))))
            ->get();

        return response()->json([
            'data' => [
                'items' => $items->map(fn (MercadoLibreItem $item) => $this->itemPayload($item))->all(),
                'total' => $total,
            ],
            'message' => null,
        ]);
    }

    /** Publicaciones sin producto local: es la bandeja de conciliación. */
    public function unmatched(Request $request): JsonResponse
    {
        $items = MercadoLibreItem::query()
            ->where('match_status', MercadoLibreItem::MATCH_UNMATCHED)
            ->withCount('variations')
            ->orderByDesc('sold_quantity')
            ->orderByDesc('id')
            ->limit(min(200, max(1, $request->integer('limit', 100))))
            ->get();

        return response()->json([
            'data' => [
                'items' => $items->map(fn (MercadoLibreItem $item) => $this->itemPayload($item))->all(),
                'total' => MercadoLibreItem::where('match_status', MercadoLibreItem::MATCH_UNMATCHED)->count(),
            ],
            'message' => null,
        ]);
    }

    /** Vincula una publicación con un producto del catálogo. */
    public function link(Request $request, MercadoLibreItem $item): JsonResponse
    {
        $datos = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        // El producto se busca con el scope de empresa activo: no se puede
        // vincular una publicación a un producto de otra empresa.
        $producto = Product::whereKey($datos['product_id'])->first();

        if ($producto === null) {
            return response()->json(['message' => 'El producto no existe en esta empresa.'], Response::HTTP_NOT_FOUND);
        }

        $this->matcher->link($item, $producto->id, 'manual');

        return response()->json([
            'data' => $this->itemPayload($item->refresh()->load('product:id,name,code')),
            'message' => 'Publicación vinculada con ' . $producto->name . '.',
        ]);
    }

    /** Desvincula (vuelve a la bandeja de conciliación). */
    public function unlink(MercadoLibreItem $item): JsonResponse
    {
        $item->forceFill([
            'match_status' => MercadoLibreItem::MATCH_UNMATCHED,
            'product_id' => null,
            'matched_by' => null,
            'matched_at' => null,
        ])->save();

        return response()->json([
            'data' => $this->itemPayload($item->refresh()),
            'message' => 'Publicación desvinculada.',
        ]);
    }

    /** Marca la publicación como "no la gestiono desde acá". */
    public function ignore(MercadoLibreItem $item): JsonResponse
    {
        $this->matcher->ignore($item);

        return response()->json([
            'data' => $this->itemPayload($item->refresh()),
            'message' => 'Publicación ignorada: no va a volver a aparecer en la conciliación.',
        ]);
    }

    /** Importa la publicación como producto nuevo del catálogo local. */
    public function importAsProduct(MercadoLibreItem $item): JsonResponse
    {
        $sku = $this->matcher->skuFromItem((array) $item->raw, (array) ($item->raw['variations'] ?? []));

        $producto = $this->matcher->importAsProduct($item, $sku);

        return response()->json([
            'data' => [
                'product_id' => $producto->id,
                'code' => $producto->code,
            ],
            'message' => 'Producto creado con el código ' . $producto->code . '.',
        ]);
    }

    /** Vuelve a intentar la vinculación por SKU de todo lo que está pendiente. */
    public function rematch(): JsonResponse
    {
        $items = MercadoLibreItem::where('match_status', MercadoLibreItem::MATCH_UNMATCHED)->get();

        $vinculados = 0;

        foreach ($items as $item) {
            $sku = $this->matcher->skuFromItem((array) $item->raw, (array) ($item->raw['variations'] ?? []));

            if ($sku !== null && $this->matcher->autoMatch($item, $sku) !== null) {
                $vinculados++;
            }
        }

        return response()->json([
            'data' => ['revisadas' => $items->count(), 'vinculadas' => $vinculados],
            'message' => $vinculados === 0
                ? 'No aparecieron nuevos productos para vincular.'
                : "Se vincularon {$vinculados} publicaciones por SKU.",
        ]);
    }

    /**
     * Importa las publicaciones de MercadoLibre (trae altas y cambios).
     *
     * Corre en el momento y devuelve el resumen de la corrida; el mismo trabajo
     * lo hace el comando programado cuando el usuario no está mirando.
     */
    public function sync(Request $request): JsonResponse
    {
        $cuenta = $this->cuentaConectada();

        if ($cuenta === null) {
            return $this->sinCuenta();
        }

        try {
            $log = $this->itemSync->import($cuenta, array_filter([
                'status' => $request->filled('status') ? (string) $request->string('status') : null,
            ]));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->logPayload($log->refresh()),
            'message' => $log->message,
        ]);
    }

    /** Empuja precio y stock de las publicaciones vinculadas. */
    public function push(Request $request): JsonResponse
    {
        $cuenta = $this->cuentaConectada();

        if ($cuenta === null) {
            return $this->sinCuenta();
        }

        try {
            $log = $this->push->pushCompany(
                $cuenta,
                (bool) $request->boolean('price', true),
                (bool) $request->boolean('stock', true),
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->logPayload($log->refresh()),
            'message' => $log->message,
        ]);
    }

    /** Empuja una sola publicación (botón "actualizar en MercadoLibre"). */
    public function pushItem(MercadoLibreItem $item): JsonResponse
    {
        $cuenta = $this->cuentaConectada();

        if ($cuenta === null) {
            return $this->sinCuenta();
        }

        try {
            $cliente = $this->client->withToken($this->oauth->freshToken($cuenta));

            $this->push->pushItem($cliente, $item->load('product'));
        } catch (\Throwable $e) {
            return response()->json([
                'data' => $this->itemPayload($item->refresh()),
                'message' => 'No se pudo actualizar en MercadoLibre: ' . $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->itemPayload($item->refresh()),
            'message' => 'Precio y stock actualizados en MercadoLibre.',
        ]);
    }

    /** Pausa o reactiva la publicación. */
    public function changeStatus(Request $request, MercadoLibreItem $item): JsonResponse
    {
        $datos = $request->validate([
            'status' => ['required', 'in:active,paused'],
        ]);

        try {
            $this->push->changeStatus($item, $datos['status']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->itemPayload($item->refresh()),
            'message' => $datos['status'] === 'paused' ? 'Publicación pausada.' : 'Publicación activada.',
        ]);
    }

    /**
     * Publica un producto del catálogo local en MercadoLibre.
     *
     * La categoría la elige el usuario: adivinarla nosotros termina en
     * publicaciones moderadas.
     */
    public function publish(Request $request, Product $product): JsonResponse
    {
        $datos = $request->validate([
            'category_id' => ['required', 'string', 'max:191'],
            'title' => ['sometimes', 'string', 'max:60'],
            'condition' => ['sometimes', 'in:new,used,not_specified'],
            'listing_type_id' => ['sometimes', 'string', 'max:60'],
        ]);

        try {
            $item = $this->push->publish($product, $datos['category_id'], array_filter([
                'title' => $datos['title'] ?? null,
                'condition' => $datos['condition'] ?? null,
                'listing_type_id' => $datos['listing_type_id'] ?? null,
            ], fn ($valor) => $valor !== null));
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->itemPayload($item),
            'message' => 'Producto publicado en MercadoLibre.',
        ]);
    }

    /** Importa las ventas de MercadoLibre como pedidos locales. */
    public function importOrders(): JsonResponse
    {
        $cuenta = $this->cuentaConectada();

        if ($cuenta === null) {
            return $this->sinCuenta();
        }

        try {
            $log = $this->orders->import($cuenta);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'data' => $this->logPayload($log->refresh()),
            'message' => $log->message,
        ]);
    }

    /** Historial de sincronizaciones: es lo que se muestra cuando algo no aparece. */
    public function logs(Request $request): JsonResponse
    {
        $logs = MercadoLibreSyncLog::query()
            ->orderByDesc('id')
            ->limit(min(100, max(1, $request->integer('limit', 30))))
            ->get();

        return response()->json([
            'data' => ['logs' => $logs->map(fn (MercadoLibreSyncLog $log) => $this->logPayload($log))->all()],
            'message' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * La publicación sin el JSON crudo: `raw` trae el payload entero de
     * MercadoLibre y no tiene por qué viajar al navegador en un listado.
     */
    private function itemPayload(MercadoLibreItem $item): array
    {
        return [
            'id' => $item->id,
            'item_id' => $item->item_id,
            'title' => $item->title,
            'status' => $item->status,
            'sub_status' => $item->sub_status,
            'price' => $item->price === null ? null : (float) $item->price,
            'available_quantity' => $item->available_quantity,
            'sold_quantity' => $item->sold_quantity,
            'category_id' => $item->category_id,
            'permalink' => $item->permalink,
            'thumbnail' => $item->thumbnail,
            'catalog_listing' => $item->catalog_listing,
            'family_name' => $item->family_name,
            'user_product_id' => $item->user_product_id,
            'product_id' => $item->product_id,
            'product_name' => $item->relationLoaded('product') ? $item->product?->name : null,
            'product_code' => $item->relationLoaded('product') ? $item->product?->code : null,
            'match_status' => $item->match_status,
            'matched_by' => $item->matched_by,
            'variations_count' => $item->variations_count ?? null,
            'ml_last_updated' => $item->ml_last_updated,
            'synced_at' => $item->synced_at,
            'last_pushed_at' => $item->last_pushed_at,
            'last_push_error' => $item->last_push_error,
        ];
    }

    private function logPayload(MercadoLibreSyncLog $log): array
    {
        return [
            'id' => $log->id,
            'type' => $log->type,
            'status' => $log->status,
            'started_at' => $log->started_at,
            'finished_at' => $log->finished_at,
            'duration_ms' => $log->duration_ms,
            'processed' => $log->processed,
            'created' => $log->created,
            'updated' => $log->updated,
            'skipped' => $log->skipped,
            'failed' => $log->failed,
            'message' => $log->message,
            'errors' => $log->errors,
        ];
    }

    private function estadisticas(int $companyId): array
    {
        $base = MercadoLibreItem::where('company_id', $companyId);

        return [
            'items' => (clone $base)->count(),
            'activas' => (clone $base)->where('status', 'active')->count(),
            'pausadas' => (clone $base)->where('status', 'paused')->count(),
            'vinculadas' => (clone $base)->where('match_status', MercadoLibreItem::MATCH_MATCHED)->count(),
            'sin_vincular' => (clone $base)->where('match_status', MercadoLibreItem::MATCH_UNMATCHED)->count(),
        ];
    }

    private function cuentaDe(int $companyId): ?MercadoLibreAccount
    {
        return MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->first();
    }

    private function cuentaConectada(): ?MercadoLibreAccount
    {
        $companyId = $this->currentCompany->id();

        if ($companyId === null) {
            return null;
        }

        $cuenta = $this->cuentaDe($companyId);

        return $cuenta !== null && $cuenta->isConnected() ? $cuenta : null;
    }

    private function sinEmpresa(): JsonResponse
    {
        return response()->json(['message' => 'No hay empresa activa.'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function sinCuenta(): JsonResponse
    {
        return response()->json(
            ['message' => 'La empresa no tiene una cuenta de MercadoLibre conectada.'],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
