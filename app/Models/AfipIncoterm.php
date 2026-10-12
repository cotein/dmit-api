<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Incoterm: término de comercio internacional del comprobante de exportación
 * (código de 3 letras: EXW, FOB, CIF...).
 *
 * La descripción salió de un archivo con el encabezado mal formado (ARCA declara
 * una sola columna y las filas traen dos), así que si alguna vez esta tabla se
 * recarga hay que mirar el informe del importador: los códigos están bien, pero el
 * encabezado del archivo de origen no es de fiar.
 *
 * Lo llena:
 *   php artisan afip:import-datos-base incoterms
 */
class AfipIncoterm extends Model
{
    use HasFactory;

    protected $table = 'afip_incoterms';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
