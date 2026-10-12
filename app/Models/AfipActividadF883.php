<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Actividad económica del nomenclador del formulario F883.
 *
 * El modelo se llama terminado en F883 por lo mismo que la tabla: el padrón del
 * WS ws_sr_padron_a100 publica OTRAS listas de actividades
 * (SUPA.A_ACTIVIDAD_RUBRO, SUPA.E_ACTIVIDAD_OCUPACIONAL) que no se mezclan con
 * ésta. El código de 6 dígitos es el del F883 y nada más.
 *
 * `descripcion_larga` es la descripción con los productos incluidos ("Incluye
 * alforfón, cebada cervecera, etc."): sirve para mostrar, no para buscar.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base actividades
 */
class AfipActividadF883 extends Model
{
    use HasFactory;

    protected $table = 'afip_actividades_f883';

    protected $fillable = [
        'codigo',
        'descripcion',
        'descripcion_larga',
    ];
}
