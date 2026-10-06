<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Provincia del COT de ARBA. La clave es el propio código de un carácter que va
 * al registro 02.
 */
class CotProvincia extends Model
{
    use HasFactory;

    protected $table = 'cot_provincias';

    protected $primaryKey = 'codigo';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['codigo', 'descripcion'];
}
