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
        header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        $token = $request->query('token');
        // Verificar si el token existe en la caché
        if (Cache::has('verification_token_' . $token)) {
            $userId = Cache::get('verification_token_' . $token);
            // Marcar el usuario como verificado
            $user = User::find($userId);
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

    function resend(Request $request)
    {

        $user = User::find($request->id);

        $user->sendEmailVerificationNotification();

        return response()->json('Re-envío de verificación de cuenta exitosa.', 200);
    }
}
