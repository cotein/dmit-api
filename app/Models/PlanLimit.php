<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El tope de un recurso dentro de un plan.
 *
 * `limit_value` NULL es SIN TOPE, igual que la ausencia de la fila: las dos cosas
 * se leen como "no hay tope" para que el plan interno, que se carga sin renglones,
 * no dependa de que existan renglones vacíos.
 */
class PlanLimit extends Model
{
    protected $fillable = [
        'plan_id',
        'resource',
        'limit_value',
    ];

    protected $casts = [
        'limit_value' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Sin tope: es lo que la pantalla muestra como "sin límite", nunca un número.
     */
    public function isUnlimited(): bool
    {
        return $this->limit_value === null;
    }
}
