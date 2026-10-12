<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cruce operación × condición frente al IVA (código de un dígito).
 *
 * Es el catálogo que dice cómo se trata el IVA de cada operación (No Gravado,
 * Exento, 0%, 10.5%, 21%, 27%...) y no duplica a AfipInscription, que lista las
 * condiciones del emisor por separado.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base operacion_condicion_iva
 */
class AfipOperacionCondicionIva extends Model
{
    use HasFactory;

    protected $table = 'afip_operacion_condicion_iva';

    protected $fillable = [
        'codigo',
        'descripcion',
        'observacion',
    ];
}
