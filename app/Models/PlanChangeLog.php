<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un cambio de plan, o un pedido de cambio que quedó registrado.
 *
 * `source` separa el cambio hecho desde la pantalla por quien administra la
 * empresa, el que hizo la configuración interna y el PEDIDO de alguien que no
 * administra (que no cambia nada por sí solo y queda para que el dueño lo vea).
 */
class PlanChangeLog extends Model
{
    public const SOURCE_CUSTOMER = 'customer';
    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_REQUESTED = 'requested';

    protected $fillable = [
        'company_id',
        'from_plan_id',
        'to_plan_id',
        'user_id',
        'reason',
        'source',
    ];
}
