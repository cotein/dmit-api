<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\MercadoLibreItem;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Vincula publicaciones de MercadoLibre con productos del catálogo local.
 *
 * La llave es el SKU: del lado de MercadoLibre sólo cuenta el atributo
 * SELLER_SKU (el `seller_custom_field` no se combina con él), y del lado nuestro
 * es `products.code`, que es único por empresa.
 *
 * Nada se vincula "a ciegas": si el SKU no existe, la publicación queda
 * `unmatched` y aparece en la pantalla de conciliación. Crear productos
 * automáticamente cuando no hay match es lo que termina duplicando catálogos.
 */
class MercadoLibreMatchService
{
    /** Atributo de MercadoLibre que lleva el SKU del vendedor. */
    private const SKU_ATTR = 'SELLER_SKU';

    /**
     * Busca el producto local por SKU. `products` no tiene scope por empresa,
     * así que el company_id se filtra a mano: sin esto, dos empresas con el
     * mismo código se pisarían.
     */
    public function findProductBySku(int $companyId, ?string $sku): ?Product
    {
        $sku = $this->normalize($sku);

        if ($sku === null) {
            return null;
        }

        return Product::query()
            ->where('company_id', $companyId)
            ->whereRaw('UPPER(TRIM(code)) = ?', [$sku])
            ->orderBy('id')
            ->first();
    }

    /**
     * Saca el SKU de una publicación: primero el atributo SELLER_SKU, después el
     * seller_custom_field (que el vendedor puede haber usado para lo mismo) y,
     * si no hay ninguno, el SKU de la primera variación.
     */
    public function skuFromItem(array $item, array $variations = []): ?string
    {
        foreach (($item['attributes'] ?? []) as $atributo) {
            if (($atributo['id'] ?? null) === self::SKU_ATTR) {
                $valor = $this->valueFromAttribute($atributo);

                if ($valor !== null) {
                    return $valor;
                }
            }
        }

        $custom = $this->normalize($item['seller_custom_field'] ?? null);

        if ($custom !== null) {
            return $custom;
        }

        foreach ($variations as $variacion) {
            $sku = $this->normalize($variacion['seller_sku'] ?? null);

            if ($sku !== null) {
                return $sku;
            }

            foreach (($variacion['attributes'] ?? []) as $atributo) {
                if (($atributo['id'] ?? null) === self::SKU_ATTR) {
                    $valor = $this->valueFromAttribute($atributo);

                    if ($valor !== null) {
                        return $valor;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Intenta vincular la publicación con un producto local. Devuelve el id del
     * producto o null si no hay match.
     */
    public function autoMatch(MercadoLibreItem $item, ?string $sku): ?int
    {
        $producto = $this->findProductBySku($item->company_id, $sku);

        if ($producto === null) {
            $item->forceFill([
                'match_status' => MercadoLibreItem::MATCH_UNMATCHED,
                'product_id' => null,
                'matched_by' => null,
                'matched_at' => null,
            ])->save();

            return null;
        }

        $this->link($item, $producto->id, 'sku');

        return $producto->id;
    }

    /** Vincula a mano (o por SKU) y deja registro de cómo se vinculó. */
    public function link(MercadoLibreItem $item, int $productId, string $by = 'manual'): MercadoLibreItem
    {
        $item->forceFill([
            'product_id' => $productId,
            'match_status' => MercadoLibreItem::MATCH_MATCHED,
            'matched_by' => $by,
            'matched_at' => now(),
        ])->save();

        return $item;
    }

    /** El usuario decidió que esta publicación no se gestiona desde acá. */
    public function ignore(MercadoLibreItem $item): MercadoLibreItem
    {
        $item->forceFill([
            'match_status' => MercadoLibreItem::MATCH_IGNORED,
            'product_id' => null,
            'matched_by' => null,
            'matched_at' => now(),
        ])->save();

        return $item;
    }

    /**
     * Crea un producto local a partir de la publicación.
     *
     * Es una acción EXPLÍCITA del usuario en la pantalla de conciliación, nunca
     * automática. Si la publicación no trae SKU se usa el id de MercadoLibre
     * como código, para poder identificarla después.
     */
    public function importAsProduct(MercadoLibreItem $item, ?string $sku = null): Product
    {
        $codigo = $this->normalize($sku) ?? strtoupper((string) $item->item_id);

        $producto = Product::create([
            'company_id' => $item->company_id,
            'name' => strtoupper(mb_substr((string) ($item->title ?: $codigo), 0, 191)),
            'code' => $codigo,
            'sub_title' => '',
            'description' => '',
            'published_meli' => 1,
            'published_here' => 0,
            'slug' => Str::slug((string) ($item->title ?: $codigo)),
            'critical_stock' => 0,
            'apply_discount' => 0,
            'apply_discount_amount' => 0,
            'apply_discount_percentage' => 0,
            'see_price_on_the_web' => 0,
        ]);

        $this->link($item, $producto->id, 'import');

        return $producto;
    }

    /**
     * Normaliza un SKU para comparar: mayúsculas y sin espacios en las puntas.
     * El catálogo local guarda los códigos en mayúsculas, así que "abc-1" y
     * "ABC-1" tienen que ser el mismo producto.
     */
    private function normalize(mixed $sku): ?string
    {
        if (! is_string($sku)) {
            return null;
        }

        $sku = mb_strtoupper(trim($sku));

        return $sku === '' ? null : $sku;
    }

    /** El valor de un atributo puede venir como string o como objeto. */
    private function valueFromAttribute(array $atributo): ?string
    {
        foreach (['value_name', 'value_id'] as $clave) {
            $valor = $atributo[$clave] ?? null;

            if (is_string($valor) && trim($valor) !== '') {
                return $this->normalize($valor);
            }

            if (is_array($valor)) {
                foreach ($valor as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        return $this->normalize($item);
                    }
                }
            }
        }

        return null;
    }
}
