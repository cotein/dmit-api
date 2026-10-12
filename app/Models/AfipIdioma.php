<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Idioma del comprobante de exportación (código de un dígito: 1 Español,
 * 2 Inglés, 3 Portugués).
 *
 * Lo llena:
 *   php artisan afip:import-datos-base idiomas
 */
class AfipIdioma extends Model
{
    use HasFactory;

    protected $table = 'afip_idiomas';

    protected $fillable = [
        'codigo',
        'descripcion',
    ];
}
