<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro crudo de una colección de parámetros de ARCA (WS ws_sr_padron_a100).
 *
 * Se guarda tal como llegó del servicio para poder rehacer el mapeo hacia las tablas
 * del sistema sin volver a pedirlo. Lo llena:
 *   php artisan afip:sync-padron-parametros
 */
class AfipParametro extends Model
{
    use HasFactory;

    protected $table = 'afip_parametros';

    protected $fillable = [
        'collection_name',
        'parametro_id',
        'descripcion',
        'atributos',
        'sincronizado_at',
    ];

    protected $casts = [
        'atributos' => 'array',
        'sincronizado_at' => 'datetime',
    ];
}
