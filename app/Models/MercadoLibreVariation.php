<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Variación de una publicación (un talle, un color).
 *
 * Se sincroniza aparte del item porque es la unidad donde MercadoLibre maneja
 * stock y SKU: `available_quantity` y el atributo SELLER_SKU viven acá. El SKU
 * de la variación es el que se cruza con products.code para vincular.
 */
class MercadoLibreVariation extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    protected $table = 'mercadolibre_variations';

    protected $fillable = [
        'company_id',
        'mercadolibre_item_id',
        'variation_id',
        'price',
        'available_quantity',
        'sold_quantity',
        'seller_sku',
        'catalog_product_id',
        'attribute_combinations',
        'attributes',
        'picture_ids',
        'ml_last_updated',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'attribute_combinations' => 'array',
        'attributes' => 'array',
        'picture_ids' => 'array',
        'ml_last_updated' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MercadoLibreItem::class, 'mercadolibre_item_id');
    }

    /**
     * Descripción legible de la variación ("Rojo / M"), armada con los valores
     * de los atributos que devuelve MercadoLibre.
     */
    public function label(): string
    {
        $partes = [];

        foreach ((array) $this->attribute_combinations as $combinacion) {
            $valor = $combinacion['value_name'] ?? null;

            if ($valor !== null && $valor !== '') {
                $partes[] = is_array($valor) ? implode(' ', $valor) : (string) $valor;
            }
        }

        return $partes === [] ? ('Variación ' . $this->variation_id) : implode(' / ', $partes);
    }
}
