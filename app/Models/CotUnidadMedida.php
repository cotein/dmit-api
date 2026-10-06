<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Unidad de medida de ARBA para el COT (un carácter, ARBA_CODIGO_UNIDAD_MEDIDA
 * del registro 03).
 */
class CotUnidadMedida extends Model
{
    use HasFactory;

    protected $table = 'cot_unidades_medida';

    protected $fillable = ['codigo', 'descripcion', 'active'];
}
