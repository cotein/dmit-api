<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila del Nomenclador de Transporte de Bienes de ARBA.
 *
 * El código es la clave natural. La tabla no tiene created_at ni updated_at: el
 * esquema es el del script que publica ARBA y se reimporta tal cual cuando ARBA
 * lo actualiza.
 *
 * El código que va al registro 03 del archivo TXT es el de tipo VARIEDAD, el
 * único de seis dígitos.
 */
class ArbaNomenclador extends Model
{
    use HasFactory;

    public const CAPITULO = 'CAPITULO';

    public const RUBRO = 'RUBRO';

    public const VARIEDAD = 'VARIEDAD';

    protected $table = 'arba_nomenclador_tb';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['codigo', 'codigo_padre', 'nivel', 'tipo', 'descripcion', 'unidad_medida'];

    protected $casts = [
        'nivel' => 'integer',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'codigo_padre', 'codigo');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'codigo_padre', 'codigo');
    }

    /**
     * Sólo los códigos que pueden ir en el registro 03 del TXT.
     */
    public function scopeProductos(Builder $query): Builder
    {
        return $query->where('tipo', self::VARIEDAD);
    }

    public function scopeCapitulos(Builder $query): Builder
    {
        return $query->where('tipo', self::CAPITULO);
    }
}
