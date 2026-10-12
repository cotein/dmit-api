<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificación recibida de Mercado Pago.
 *
 * A diferencia del resto del dominio NO usa BelongsToCompanyTrait: el webhook
 * llega sin usuario autenticado y CompanyScope es fail-closed (devolvería cero
 * filas siempre). La empresa se resuelve después, cruzando el cobro referenciado.
 *
 * `mp_event_id` es único → un reintento del mismo evento no se procesa dos veces.
 */
class MercadoPagoWebhookEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'mp_event_id',
        'topic',
        'action',
        'resource_id',
        'company_id',
        'signature_valid',
        'payload',
        'status',
        'attempts',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
    ];

    /**
     * Cierra el evento dejando constancia del intento.
     *
     * El estado se pasa explícitamente porque hay dos finales que NO son lo
     * mismo: 'processed' (movimos datos del negocio) e 'ignored' (no había nada
     * que hacer). Pisar el segundo con el primero dejaba la bitácora mintiendo.
     */
    public function markProcessed(string $status = self::STATUS_PROCESSED): void
    {
        $this->update([
            'status' => $status,
            'attempts' => $this->attempts + 1,
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'attempts' => $this->attempts + 1,
            'error' => mb_substr($error, 0, 1000),
        ]);
    }
}
