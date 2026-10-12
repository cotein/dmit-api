<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida de ARCA (código de 2 dígitos).
 *
 * No confundir con CotUnidadMedida: ésa es la de ARBA para el COT y su código es
 * de un carácter. Son catálogos de organismos distintos que conviven en el sistema.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base unidades
 */
class AfipUnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'afip_unidades_medida';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
