<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La suscripción de un CUIT a un plan: una sola fila por empresa.
 *
 * NO lleva BelongsToCompanyTrait a propósito. El consumo y el plan los tocan tanto
 * los pedidos HTTP como los comandos de consola (saneamiento de reservas,
 * recálculo, backfill), y CompanyScope es fail-closed: en consola devolvería cero
 * filas, el servicio leería "sin suscripción" y el mismo contribuyente pasaría de
 * tener un plan a no tener ninguno según desde dónde se pregunte. Acá la empresa
 * se pasa SIEMPRE explícita.
 */
class CompanyPlan extends Model
{
    /** La suscripción está vigente: rige el plan asignado. */
    public const STATUS_ACTIVE = 'active';

    /** Venció: cae al plan gratuito, con motivo. */
    public const STATUS_EXPIRED = 'expired';

    /** Suspendida: cae al plan gratuito, con motivo. */
    public const STATUS_SUSPENDED = 'suspended';

    /** Cancelada por el cliente: cae al plan gratuito, con motivo. */
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'plan_id',
        'status',
        'started_at',
        'expires_at',
        'suspended_at',
        'assigned_by',
        'requested_plan_id',
        'requested_at',
        'requested_by',
        'gating_enabled',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'suspended_at' => 'datetime',
        'requested_at' => 'datetime',
        'gating_enabled' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    /**
     * ¿La suscripción está vigente hoy?
     *
     * Una fila activa con `expires_at` ya pasado cuenta como vencida: el estado lo
     * dice la fecha, no sólo la etiqueta, porque el vencimiento puede llegar entre
     * dos corridas de cualquier proceso.
     */
    public function isCurrent(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * El motivo por el que el plan no está vigente, o null si no hay degradación.
     *
     * Es lo que la pantalla muestra como problema administrativo. Viaja APARTE del
     * rechazo por tope: son dos problemas distintos y no comparten mensaje.
     */
    public function lapseReason(): ?string
    {
        if ($this->status === self::STATUS_ACTIVE && ! $this->isCurrent()) {
            return self::STATUS_EXPIRED;
        }

        if (in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_SUSPENDED, self::STATUS_CANCELLED], true)) {
            return $this->status;
        }

        return null;
    }
}
