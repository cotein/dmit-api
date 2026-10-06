<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cache;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{

    public function verify_email(Request $request)
    {
        // Los headers de CORS los maneja config/cors.php (HandleCors), no el controlador.
        $token = $request->query('token');
        // Verificar si el token existe en la caché
        if (Cache::has('verification_token_' . $token)) {
            $userId = Cache::get('verification_token_' . $token);
            // Marcar el usuario como verificado
            $user = User::find($userId);

            if (! $user) {
                Cache::forget('verification_token_' . $token);

                return response()->json(['message' => 'El enlace de verificación es inválido o ha caducado.'], 400);
            }

            $user->email_verified_at = now();
            $user->active = true;
            $user->save();
            // Eliminar el token de la caché
            Cache::forget('verification_token_' . $token);

            // Redirigir al usuario a la página de inicio del sistema de facturación
            return response()->json(['message' => 'Verificación correcta. ¡Bienvenido, ' . $user->name . '!'], 200);
        }
        // Si el token no es válido o ha caducado, mostrar un mensaje de error
        return response()->json(['message' => 'El enlace de verificación es inválido o ha caducado.'], 400);
    }

    /**
     * Reenvía el mail de verificación al usuario AUTENTICADO.
     * Antes aceptaba un 'id' arbitrario del body: cualquiera podía disparar mails
     * a cualquier usuario sin autenticarse.
     */
    public function resend(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Re-envío de verificación de cuenta exitosa.'], 200);
    }
}
