<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan de suscripción de DMIT. Es de plataforma: no pertenece a ninguna empresa
 * y por eso no lleva CompanyScope (si lo llevara, un usuario sin empresa no
 * podría ni listar los planes).
 */
class SubscriptionPlan extends Model
{
    use HasFactory;

    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY = 'yearly';

    protected $fillable = [
        'name',
        'description',
        'amount',
        'currency',
        'period',
        'free_trial_days',
        'mp_preapproval_plan_id',
        'active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'free_trial_days' => 'integer',
        'active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
