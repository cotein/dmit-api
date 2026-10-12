<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Otro tributo del comprobante (el catálogo de FEParamGetTiposTributos, código de
 * 2 dígitos): lo que se informa ADEMÁS del IVA.
 *
 * `observacion` es la condición con la que se puede usar cada código ("Sólo
 * Controladores Fiscales", "Liquidaciones Pecuarias", "Solo FE") y viene vacía en
 * los códigos de uso general.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base tributos
 */
class AfipOtroTributo extends Model
{
    use HasFactory;

    protected $table = 'afip_otros_tributos';

    protected $fillable = [
        'codigo',
        'descripcion',
        'observacion',
    ];
}
