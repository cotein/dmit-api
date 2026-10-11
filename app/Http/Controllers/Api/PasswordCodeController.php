<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Models\AccountCode;
use App\Models\User;
use App\Src\Services\Account\AccountCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Restablecer la contraseña con un código de 6 dígitos que llega por correo.
 *
 * Convive a propósito con el flujo viejo (`forgotPassword/reset/code` + el enlace
 * con token): ese sigue atendiendo los mails ya enviados y este es el camino nuevo,
 * que no depende de que el usuario vuelva al mismo navegador donde lo pidió.
 *
 * Las dos rutas son PÚBLICAS (quien perdió la contraseña no tiene sesión) y por eso
 * llevan throttle: sin eso, se pueden usar para inundar de mails a cualquiera.
 */
class PasswordCodeController extends Controller
{
    public function __construct(private AccountCodeService $codes)
    {
    }

    /**
     * Pide el código. La respuesta es SIEMPRE la misma, exista o no el correo:
     * si dijera "ese correo no existe", cualquiera podría averiguar quién está
     * registrado probando direcciones.
     */
    public function request(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
        ]);

        $this->codes->request($data['email'], AccountCode::PURPOSE_PASSWORD_RESET, $request->ip());

        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un código de 6 dígitos.',
        ]);
    }

    /**
     * Cambia la contraseña usando el código.
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
            'password' => 'required|string|min:8',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'code.required' => 'El código es obligatorio.',
            'code.digits' => 'El código son 6 dígitos.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña tiene que tener al menos 8 caracteres.',
        ]);

        $record = $this->codes->verify($data['email'], $data['code'], AccountCode::PURPOSE_PASSWORD_RESET);

        if (! $record) {
            // Un solo mensaje para "no existe", "venció" y "está mal": no se filtra
            // si el correo existe ni si el código estuvo cerca de ser el correcto.
            return response()->json([
                'message' => 'El código es incorrecto o ya venció. Pedí uno nuevo.',
            ], 422);
        }

        $user = User::find($record->user_id);

        if (! $user) {
            return response()->json(['message' => 'No pudimos restablecer la contraseña.'], 422);
        }

        $user->password = bcrypt($data['password']);
        $user->save();

        $this->codes->consume($record);

        // Si no se revocan, el token que ya estaba afuera (el de un atacante, o el
        // del navegador de otro dispositivo) sigue entrando con la contraseña vieja.
        $revoked = $user->revokeAllApiTokens();

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user));
        } catch (\Throwable $e) {
            // El cambio YA se hizo: si el aviso no sale, no se le puede decir al
            // usuario que falló.
            Log::error('Cuenta: no se pudo enviar el aviso de cambio de contraseña', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Cuenta: contraseña restablecida con código', [
            'user_id' => $user->id,
            'tokens_revocados' => $revoked,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada. Entrá con tu contraseña nueva.',
        ]);
    }
}
