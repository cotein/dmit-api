<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una fila del puente entre la unidad de medida del nomenclador y el código de
 * un carácter que espera ARBA.
 *
 * La clave es la descripción tal como viene en el nomenclador, sin normalizar:
 * es lo que permite resolver el código desde el producto con un solo lookup.
 */
class CotMapeoUnidadNomenclador extends Model
{
    protected $table = 'cot_mapeo_unidad_nomenclador';

    protected $primaryKey = 'descripcion_nomenclador';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['descripcion_nomenclador', 'codigo_unidad'];

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(CotUnidadMedida::class, 'codigo_unidad', 'codigo');
    }

    /**
     * Código de un carácter para una descripción del nomenclador.
     *
     * Devuelve null cuando no hay mapeo: el que llama decide qué hacer, pero
     * nunca se inventa un código.
     */
    public static function codigoPara(?string $descripcion): ?string
    {
        $clave = static::normalizar($descripcion);

        if ($clave === '') {
            return null;
        }

        $fila = static::query()->where('descripcion_nomenclador', $clave)->first();

        return $fila?->codigo_unidad;
    }

    /**
     * El nomenclador escribe las unidades en plural con paréntesis
     * ("KILOGRAMO(S)", "METRO(S) CUBICO(S)"); la tabla las guarda en singular.
     * Se saca el sufijo plural y se colapsan los espacios, así una sola fila
     * del mapeo cubre las dos formas.
     */
    public static function normalizar(?string $descripcion): string
    {
        $texto = mb_strtoupper(trim((string) $descripcion));

        if ($texto === '') {
            return '';
        }

        $texto = preg_replace('/\(\s*(S|ES)\s*\)/i', '', $texto) ?? $texto;
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }
}
