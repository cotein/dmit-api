<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta de MercadoLibre conectada por una empresa.
 *
 * Los tokens se guardan con cast 'encrypted' (usa APP_KEY) y están en $hidden:
 * nunca salen en un JSON de API, ni siquiera para el dueño de la empresa.
 *
 * OJO con el refresh: MercadoLibre invalida el refresh token anterior cada vez
 * que se usa ("sólo vale el último"). Todo refresh tiene que pasar por
 * MercadoLibreOAuthService::refresh(), que bloquea la fila antes de tocar la
 * cadena; dos procesos refrescando a la vez dejan la cuenta inservible y obligan
 * al vendedor a reconectar a mano.
 */
class MercadoLibreAccount extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_ERROR = 'error';

    protected $table = 'mercadolibre_accounts';

    protected $fillable = [
        'company_id',
        'ml_user_id',
        'site_id',
        'nickname',
        'email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'connected_at',
        'last_refreshed_at',
        'last_synced_at',
        'last_error',
        'user_product_seller',
        'raw',
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
        'last_synced_at' => 'datetime',
        'user_product_seller' => 'boolean',
        'raw' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MercadoLibreItem::class, 'mercadolibre_account_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(MercadoLibreSyncLog::class, 'mercadolibre_account_id');
    }

    /** ¿El token sirve todavía? Se renueva antes de vencer, no cuando falla la llamada. */
    public function tokenExpiresSoon(int $marginMinutes = 60): bool
    {
        if ($this->token_expires_at === null) {
            return true;
        }

        return $this->token_expires_at->subMinutes($marginMinutes)->isPast();
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED && ! empty($this->access_token);
    }
}
