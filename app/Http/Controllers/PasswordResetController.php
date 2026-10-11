<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Restablecer la contraseña con un ENLACE (el flujo de siempre).
 *
 * Convive a propósito con el flujo nuevo por código de 6 dígitos
 * (`PasswordCodeController`): este atiende los enlaces que ya salieron por mail y
 * a quien elige "prefiero un enlace". El token vive en la cache 10 minutos, sirve
 * una sola vez y se borra al usarse.
 *
 * ARREGLOS de esta vuelta:
 *  - el mail ya no lo manda el microservicio de emails (quedó sin dominio propio,
 *    así que ningún mail de cuenta podía salir): ahora sale por el mailer de Laravel;
 *  - el pedido ya no revela si el correo existe (antes respondía "éste correo
 *    electrónico no existe en nuestros registros", y eso deja averiguar quién está
 *    registrado probando direcciones);
 *  - al cambiar la contraseña se revocan TODOS los tokens de API del usuario.
 */
class PasswordResetController extends Controller
{
    private const TOKEN_TTL_MINUTES = 10;

    public function passwordResetCode(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección de correo válida.',
        ]);

        // La misma respuesta para todos los casos (exista o no el correo, y aunque
        // falle el envío): es lo que evita la enumeración de usuarios.
        $message = 'Si el correo está registrado, te enviamos las instrucciones para restablecer tu contraseña.';

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            Log::info('Cuenta: se pidió el enlace de restablecimiento para un correo no registrado');

            return response()->json(['message' => $message]);
        }

        $token = Str::random(131);

        Cache::put('password_reset_token_' . $token, $user->email, now()->addMinutes(self::TOKEN_TTL_MINUTES));

        $url = config('app.frontend_url') . '/restablecer-contrasena?token=' . $token;

        try {
            Mail::to($user->email)->send(new PasswordResetLinkMail($user, $url));
        } catch (\Throwable $e) {
            Log::error('Cuenta: no se pudo enviar el mail de restablecimiento', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => $message]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:8',
        ], [
            'token.required' => 'El token es obligatorio.',
            'token.string' => 'El token debe ser una cadena de texto.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.string' => 'La contraseña debe ser una cadena de texto.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        // Buscar el correo asociado al token en la caché
        $email = Cache::get('password_reset_token_' . $data['token']);

        if (! $email) {
            return response()->json(['error' => 'Token inválido o expirado'], 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        $user->password = bcrypt($data['password']);
        $user->save();

        Cache::forget('password_reset_token_' . $data['token']);

        // Sin esto, el token que ya estaba afuera sigue entrando como si nada.
        $revoked = $user->revokeAllApiTokens();

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user));
        } catch (\Throwable $e) {
            // La contraseña YA cambió: si el aviso no sale, no se le puede decir
            // al usuario que falló.
            Log::error('Cuenta: no se pudo enviar el aviso de cambio de contraseña', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Cuenta: contraseña restablecida con enlace', [
            'user_id' => $user->id,
            'tokens_revocados' => $revoked,
        ]);

        return response()->json(['message' => 'Contraseña actualizada correctamente'], 200);
    }
}
