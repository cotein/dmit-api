<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una reserva de consumo: el lugar que se toma antes de llamar al organismo.
 *
 * `key_hash` es la clave de idempotencia del intento (hash, nunca el contenido del
 * pedido). Su índice único por empresa y recurso es lo que impide que un reintento
 * consuma dos veces.
 */
class PlanUsageReservation extends Model
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_RELEASED = 'released';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'period',
        'resource',
        'key_hash',
        'status',
        'expires_at',
        'confirmed_at',
        'invoice_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    /**
     * ¿Esta reserva sigue ocupando lugar?
     *
     * Sólo la reserva viva ocupa. La vencida no: si el proceso se cortó entre la
     * reserva y la confirmación, el lugar tiene que volver solo.
     */
    public function isHolding(): bool
    {
        return $this->status === self::STATUS_RESERVED && $this->expires_at->isFuture();
    }
}
