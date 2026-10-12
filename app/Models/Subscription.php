<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suscripción de una empresa a un plan de DMIT (preapproval de Mercado Pago).
 * El estado autoriza o no el acceso al sistema: se actualiza por webhook.
 */
class Subscription extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_AUTHORIZED = 'authorized';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'plan_id',
        'payer_user_id',
        'mp_preapproval_id',
        'payer_email',
        'status',
        'started_at',
        'next_payment_at',
        'last_payment_at',
        'cancelled_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_AUTHORIZED;
    }
}
