<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * País (y zona franca) de la TABLA 20 PAISES de ARCA.
 *
 * `pais` no repite siempre `descripcion`: las zonas francas llevan el país del que
 * dependen (ZF Iquique es CHILE, AAE Tierra del Fuego es ARGENTINA), así que quien
 * muestre un país de este catálogo tiene que decidir cuál de las dos columnas usa.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base paises
 */
class AfipPais extends Model
{
    use HasFactory;

    protected $table = 'afip_paises';

    protected $fillable = [
        'codigo',
        'descripcion',
        'pais',
    ];
}
