<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Notificación recibida de MercadoLibre, tal como llegó.
 *
 * Se guarda cruda y se responde 200 enseguida (MercadoLibre corta a los 500 ms y
 * reintenta hasta 8 veces en una hora). El procesamiento real lo hace la
 * conciliación leyendo esta tabla.
 *
 * El payload no se cree nunca: es un aviso. El dato se pide después a la API con
 * el token de la empresa, así una notificación falsa no ensucia el espejo local.
 */
class MercadoLibreWebhookEvent extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_FAILED = 'failed';

    protected $table = 'mercadolibre_webhook_events';

    protected $fillable = [
        'company_id',
        'mercadolibre_account_id',
        'notification_id',
        'topic',
        'ml_user_id',
        'resource',
        'site_id',
        'attempts',
        'sent_at',
        'status',
        'processed_at_ms',
        'error',
        'payload',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
