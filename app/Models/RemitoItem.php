<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Renglón de un remito.
 *
 * La tabla guarda producto y cantidad, que es lo que exige el Anexo V, inciso
 * III ("descripción, contenido y cantidad de los bienes transportados"), más
 * los importes que ya traía la tabla original.
 *
 * La descripción que se imprime NO sale de `products.name`: sale del snapshot
 * del remito. Si el producto se renombra después, el remito reimpreso tiene que
 * seguir diciendo lo que decía el día que se entregó.
 */
class RemitoItem extends Model
{
    use HasFactory;

    protected $table = 'remito_items';

    protected $fillable = [
        'remito_id',
        'product_id',
        'quantity',
        'neto',
        'iva',
        'total',
    ];

    protected $casts = [
        'quantity' => 'float',
        'neto' => 'float',
        'iva' => 'float',
        'total' => 'float',
    ];

    public function remito(): BelongsTo
    {
        return $this->belongsTo(Remito::class, 'remito_id', 'id');
    }

    public function product(): HasOne
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }
}
