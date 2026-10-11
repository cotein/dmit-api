<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Código de un solo uso para una acción de la cuenta.
 *
 * Se guarda hasheado (`code_hash`), con vencimiento, contador de intentos y la
 * marca de uso. Hoy se usa para restablecer la contraseña; `purpose` ya contempla
 * los flujos de la Fase 2 (segundo factor por mail y entrar con un código).
 */
class AccountCode extends Model
{
    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_TWO_FACTOR_EMAIL = 'two_factor_email';

    public const PURPOSE_LOGIN_CODE = 'login_code';

    protected $fillable = [
        'user_id',
        'email',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'used_at',
        'requested_ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /** ¿Sirve todavía? Vencido, usado o agotado no sirve. */
    public function isUsable(): bool
    {
        return ! $this->isUsed() && ! $this->isExpired();
    }
}
