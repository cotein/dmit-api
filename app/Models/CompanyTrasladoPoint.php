<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Establecimiento habilitado en ARBA, identificado por el par planta + puerta
 * que va en el nombre del archivo TXT.
 *
 * Una empresa puede tener varios: por eso es una tabla y no dos columnas en
 * companies.
 */
class CompanyTrasladoPoint extends Model
{
    use HasFactory;

    protected $table = 'company_traslado_points';

    protected $fillable = [
        'name',
        'company_id',
        'planta',
        'puerta',
        'street',
        'number',
        'comple',
        'floor',
        'apartment',
        'neighbourhood',
        'codigo_postal',
        'city',
        'state_id',
        'active',
    ];

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function state(): HasOne
    {
        return $this->hasOne(AfipState::class, 'id', 'state_id');
    }
}
