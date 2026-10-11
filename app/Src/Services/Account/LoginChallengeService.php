<?php

namespace App\Src\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * El "pase" que viaja entre el primer factor y el segundo.
 *
 * Cuando el usuario pone bien la contraseña (o entra con Google) y tiene doble
 * factor, el login NO devuelve todavía un token de la API: devuelve este challenge,
 * firmado con la APP_KEY y con vencimiento. Así el segundo paso no necesita sesión
 * ni guardar nada en la base, y nadie puede inventarse un challenge a mano.
 */
class LoginChallengeService
{
    private const TTL_MINUTES = 5;

    public function issue(User $user): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'exp' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ]));
    }

    /** Devuelve el usuario si el challenge es válido y no venció; si no, null. */
    public function consume(string $token): ?User
    {
        try {
            $datos = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            // Token manipulado o de otra instalación: se trata como inválido.
            return null;
        }

        if (! is_array($datos) || empty($datos['user_id']) || empty($datos['exp'])) {
            return null;
        }

        if (now()->timestamp > (int) $datos['exp']) {
            return null;
        }

        return User::find((int) $datos['user_id']);
    }
}
