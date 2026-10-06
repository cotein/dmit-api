<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de mercadería de un COT: el registro 03 del archivo TXT.
 *
 * cantidades con 2 decimales: el TXT las pide con decimales obligatorios, así
 * que si la cantidad es 200 se envía 20000.
 */
class CotItem extends Model
{
    use HasFactory;

    protected $table = 'cot_items';

    protected $fillable = [
        'cot_id',
        'codigo_unico_producto',
        'arba_codigo_unidad_medida',
        'cantidad',
        'propio_codigo_producto',
        'propio_descripcion_producto',
        'propio_descripcion_unidad_medida',
        'cantidad_ajustada',
    ];

    protected $casts = [
        'cantidad' => 'decimal:2',
        'cantidad_ajustada' => 'decimal:2',
    ];

    public function cot(): BelongsTo
    {
        return $this->belongsTo(Cot::class, 'cot_id', 'id');
    }
}
