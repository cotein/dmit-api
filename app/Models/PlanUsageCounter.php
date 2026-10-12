<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El consumo de un CUIT en un mes, por recurso.
 *
 * Sin BelongsToCompanyTrait por el mismo motivo que CompanyPlan: lo leen y lo
 * escriben pedidos HTTP y comandos de consola, y el scope fail-closed haría que el
 * recálculo viera cero consumo y "corrigiera" lo que no hay que corregir.
 *
 * La fila no se borra nunca: el número real del mes queda. Cuando alguien baja de
 * plan con el consumo ya pasado (40 contra un tope de 10), la pantalla tiene que
 * poder explicarlo sin parecer una falla.
 */
class PlanUsageCounter extends Model
{
    protected $fillable = [
        'company_id',
        'period',
        'resource',
        'used',
    ];

    protected $casts = [
        'used' => 'integer',
    ];
}
