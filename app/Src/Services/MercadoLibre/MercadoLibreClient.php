<?php

namespace App\Src\Services\MercadoLibre;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP de MercadoLibre. Es la única pieza que habla con la API.
 *
 * Se construye con UN access token: el de una empresa (OAuth). Todo lo que no
 * sea transporte —qué se importa, qué se empuja, a qué producto se vincula—
 * vive en los servicios de dominio, así el transporte se puede probar solo.
 *
 * Tres reglas de MercadoLibre están codificadas acá:
 *   1. El límite es por aplicación y por endpoint; al excederse devuelve 429.
 *      Reintentamos con backoff exponencial + jitter y respetando Retry-After.
 *   2. La búsqueda del vendedor devuelve SOLO ids y, para catálogos grandes,
 *      usa un `scroll_id` que vence a los 5 minutos. Si vence, se reinicia el
 *      rastreo (por eso quien consume tiene que ser idempotente).
 *   3. El detalle en lote va de a 20 ids por llamada.
 */
class MercadoLibreClient
{
    public function __construct(private ?string $accessToken = null)
    {
    }

    public function withToken(string $accessToken): self
    {
        return new self($accessToken);
    }

    public function withoutToken(): self
    {
        return new self(null);
    }

    public function get(string $path, array $query = []): array
    {
        return $this->send('get', $path, ['query' => $query]);
    }

    public function post(string $path, array $payload = []): array
    {
        return $this->send('post', $path, ['json' => $payload]);
    }

    /**
     * POST con cuerpo de formulario.
     *
     * El endpoint de tokens de MercadoLibre no acepta JSON: espera
     * application/x-www-form-urlencoded, tanto para el canje del código como
     * para el refresh.
     */
    public function postForm(string $path, array $payload = []): array
    {
        return $this->send('post', $path, ['form' => $payload]);
    }

    public function put(string $path, array $payload = []): array
    {
        return $this->send('put', $path, ['json' => $payload]);
    }

    public function delete(string $path, array $payload = []): array
    {
        return $this->send('delete', $path, ['json' => $payload]);
    }

    /*
    |--------------------------------------------------------------------------
    | Operaciones de dominio
    |--------------------------------------------------------------------------
    */

    /** Los datos del vendedor dueño del token: de acá sale el user id y el tag de UPs. */
    public function usersMe(): array
    {
        return $this->get('users/me');
    }

    /** Detalle de una publicación, con sus variaciones. */
    public function item(string $itemId, array $attributes = []): array
    {
        $query = $attributes === [] ? [] : ['attributes' => implode(',', $attributes)];

        return $this->get('items/' . $itemId, $query);
    }

    /**
     * Detalle de varias publicaciones en una sola llamada: 20 como máximo.
     *
     * OJO: el viejo `/items?ids=` queda deprecado (se apaga el 25/10/2026); se
     * usa `/items/bulk`, que devuelve `status_code` en lugar de `code`.
     *
     * @param  string[]  $itemIds
     * @return array<int, array<string, mixed>>
     */
    public function bulkItems(array $itemIds): array
    {
        $resultado = [];
        $tope = (int) config('mercadolibre.sync.bulk_chunk', 20);

        foreach (array_chunk(array_values(array_unique($itemIds)), $tope) as $lote) {
            $respuesta = $this->get('items/bulk', [
                'ids' => implode(',', $lote),
                'attributes' => 'body.id,body.title,body.price,body.status,body.sub_status,body.available_quantity,'
                    . 'body.sold_quantity,body.category_id,body.domain_id,body.listing_type_id,body.condition,'
                    . 'body.currency_id,body.permalink,body.thumbnail,body.variations,body.attributes,body.tags,'
                    . 'body.catalog_listing,body.catalog_product_id,body.family_name,body.user_product_id,'
                    . 'body.health,body.last_updated,body.base_price',
            ]);

            foreach ($respuesta as $entrada) {
                // `id` viene en la raíz y el cuerpo adentro de `body`.
                $id = $entrada['id'] ?? ($entrada['body']['id'] ?? null);

                if ($id !== null) {
                    $resultado[$id] = $entrada['body'] ?? $entrada;
                }
            }
        }

        return $resultado;
    }

    /**
     * Todos los ids de las publicaciones del vendedor, por estado.
     *
     * MercadoLibre devuelve sólo ids en el listado. Para catálogos grandes hay
     * que usar `search_type=scan`: devuelve un `scroll_id` que se va pasando
     * hasta que no devuelve más resultados. Ese scroll vence a los 5 minutos;
     * si se vence, se reinicia el rastreo una vez para no perder publicaciones.
     *
     * @return \Generator<int, string>
     */
    public function scanItemIds(int $userId, array $filtros = []): \Generator
    {
        $intento = 0;

        while ($intento < 2) {
            $intento++;
            $scrollId = null;
            $inicio = microtime(true);
            $ids = 0;

            do {
                $query = $filtros;

                if ($scrollId === null) {
                    $query['search_type'] = 'scan';
                    $query['limit'] = (int) config('mercadolibre.sync.page_size', 100);
                } else {
                    $query['scroll_id'] = $scrollId;
                }

                $respuesta = $this->get("users/{$userId}/items/search", $query);

                $scrollId = $respuesta['scroll_id'] ?? null;

                foreach (($respuesta['results'] ?? []) as $id) {
                    $ids++;
                    yield (string) $id;
                }

                // Sin scroll nuevo no hay más páginas que recorrer.
                if ($scrollId === null || $scrollId === '') {
                    return;
                }

                // El scroll expira a los 5 minutos: se corta antes y se reinicia.
                $transcurrido = microtime(true) - $inicio;
                if ($transcurrido > ((int) config('mercadolibre.sync.scroll_ttl_minutes', 5) - 1) * 60) {
                    Log::warning('MercadoLibre: el scroll venció a mitad del rastreo, se reinicia', [
                        'user_id' => $userId,
                        'items_leidos' => $ids,
                    ]);

                    break;
                }
            } while (true);
        }
    }

    /** Descripción de la publicación: no viene en el listado ni en el detalle. */
    public function itemDescription(string $itemId): array
    {
        try {
            return $this->get("items/{$itemId}/description");
        } catch (MercadoLibreException $e) {
            // Una publicación sin descripción devuelve 404: no es un error real.
            if ($e->statusCode() === 404) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * Ventas del vendedor (órdenes) dentro de una ventana de fechas.
     *
     * El listado también devuelve sólo ids y se pagina con offset.
     *
     * @return array{results: string[], total: int}
     */
    public function searchOrderIds(int $userId, array $query = []): array
    {
        $respuesta = $this->get("orders/search", array_merge([
            'seller' => $userId,
            'sort' => 'date_desc',
            'limit' => (int) config('mercadolibre.sync.orders_page_size', 50),
        ], $query));

        $ids = [];

        foreach (($respuesta['results'] ?? []) as $orden) {
            if (isset($orden['id'])) {
                $ids[] = (string) $orden['id'];
            }
        }

        return [
            'results' => $ids,
            'total' => (int) ($respuesta['paging']['total'] ?? count($ids)),
        ];
    }

    /** Detalle de una orden de venta. */
    public function order(string $orderId): array
    {
        return $this->get('orders/' . $orderId);
    }

    /** Revoca los permisos de la aplicación para el vendedor (desconectar la cuenta). */
    public function revoke(int $userId): array
    {
        return $this->delete("users/{$userId}/applications/" . config('mercadolibre.app_id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Transporte
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{query?: array, json?: array}  $opciones
     */
    private function send(string $metodo, string $path, array $opciones = []): array
    {
        $intentos = max(1, (int) config('mercadolibre.sync.max_retries', 4));
        $respuesta = null;

        for ($intento = 1; $intento <= $intentos; $intento++) {
            $respuesta = $this->request($metodo, $path, $opciones);

            if (! $this->debeReintentar($respuesta)) {
                break;
            }

            if ($intento === $intentos) {
                break;
            }

            $espera = $this->esperaMs($respuesta, $intento);

            Log::warning('MercadoLibre: se reintenta la llamada', [
                'path' => $path,
                'status' => $respuesta->status(),
                'intento' => $intento,
                'espera_ms' => $espera,
            ]);

            usleep($espera * 1000);
        }

        return $this->handle($respuesta, $path);
    }

    private function request(string $metodo, string $path, array $opciones): Response
    {
        $peticion = Http::acceptJson()
            ->timeout(30)
            ->retry(1, 0, throw: false);

        if ($this->accessToken !== null) {
            $peticion = $peticion->withToken($this->accessToken);
        }

        $url = rtrim((string) config('mercadolibre.api.base_url'), '/') . '/' . ltrim($path, '/');

        try {
            return match ($metodo) {
                'get' => $peticion->get($url, $opciones['query'] ?? []),
                'post' => isset($opciones['form'])
                    ? $peticion->asForm()->post($url, $opciones['form'])
                    : $peticion->post($url, $opciones['json'] ?? []),
                'put' => $peticion->put($url, $opciones['json'] ?? []),
                'delete' => $peticion->delete($url, $opciones['json'] ?? []),
                default => throw new MercadoLibreException("Método HTTP no soportado: {$metodo}"),
            };
        } catch (MercadoLibreException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new MercadoLibreException(
                'No se pudo contactar a MercadoLibre: ' . $e->getMessage(),
                0,
                [],
                $e,
            );
        }
    }

    /**
     * Se reintenta cuando el problema es del otro lado o de la cuota: 429, 5xx
     * y los cortes de red. Un 400 no se reintenta nunca (el pedido está mal).
     */
    private function debeReintentar(Response $respuesta): bool
    {
        return $respuesta->status() === 429 || $respuesta->serverError();
    }

    /**
     * Backoff exponencial con jitter. Se respeta `Retry-After` si MercadoLibre
     * lo manda, que es más confiable que nuestra estimación.
     */
    private function esperaMs(Response $respuesta, int $intento): int
    {
        $retryAfter = $respuesta->header('Retry-After');

        if ($retryAfter !== '' && is_numeric($retryAfter)) {
            return min((int) $retryAfter * 1000, 30000);
        }

        $base = (int) config('mercadolibre.sync.backoff_base_ms', 500);
        $techo = $base * (2 ** ($intento - 1));

        return min($techo, 15000) + random_int(0, (int) ($base / 2));
    }

    private function handle(?Response $respuesta, string $path): array
    {
        if ($respuesta === null) {
            throw new MercadoLibreException('MercadoLibre no devolvió respuesta para ' . $path);
        }

        $cuerpo = $respuesta->json();
        $datos = is_array($cuerpo) ? $cuerpo : [];

        if ($respuesta->successful()) {
            return $datos;
        }

        $mensaje = $datos['message'] ?? ('MercadoLibre devolvió HTTP ' . $respuesta->status());

        throw new MercadoLibreException(
            $mensaje . ' (' . $path . ')',
            $respuesta->status(),
            $datos,
        );
    }
}
