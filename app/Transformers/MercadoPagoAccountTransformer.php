<?php

namespace App\Transformers;

use App\Models\MercadoPagoAccount;
use League\Fractal\TransformerAbstract;

/**
 * Estado de la conexión de una empresa, para la pantalla de configuración.
 * Nunca expone tokens: para eso están $hidden y el cast 'encrypted'.
 */
class MercadoPagoAccountTransformer extends TransformerAbstract
{
    public function transform(MercadoPagoAccount $account): array
    {
        return [
            'id' => (int) $account->id,
            'status' => $account->status,
            'nickname' => $account->nickname,
            'email' => $account->email,
            'mp_user_id' => $account->mp_user_id,
            'public_key' => $account->public_key,
            'connected_at' => $account->connected_at?->toIso8601String(),
            'token_expires_at' => $account->token_expires_at?->toIso8601String(),
            'last_error' => $account->last_error,
            'is_connected' => $account->isConnected(),
        ];
    }
}
