<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de Mercado Pago conectada por una empresa (marketplace OAuth).
 *
 * Los tokens se guardan con cast 'encrypted' (usa APP_KEY) y están en $hidden:
 * nunca salen en un JSON de API, ni siquiera para el dueño de la empresa.
 */
class MercadoPagoAccount extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'company_id',
        'mp_user_id',
        'store_external_id',
        'pos_external_id',
        'nickname',
        'email',
        'public_key',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'connected_at',
        'last_refreshed_at',
        'last_error',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
    ];

    public function charges(): HasMany
    {
        return $this->hasMany(MercadoPagoCharge::class, 'company_id', 'company_id');
    }

    /** ¿El token sirve todavía? Se renueva antes de vencer, no cuando falla el cobro. */
    public function tokenExpiresSoon(int $marginMinutes = 30): bool
    {
        if ($this->token_expires_at === null) {
            return false;
        }

        return $this->token_expires_at->subMinutes($marginMinutes)->isPast();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && ! empty($this->access_token);
    }
}
