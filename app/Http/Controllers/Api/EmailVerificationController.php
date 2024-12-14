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
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{

    public function verify(Request $request, $id, $hash)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json('Usuario no encontrado', 404);
        }

        if ($user->email_verified_at) {
            return response()->json('Cuenta ya verificada, inicie sesión en el Sistema', 200);
        }

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json('Hash de verificación no coincide', 400);
        }
        /* Log::info('hasValidRelativeSignature ' . $request->hasValidRelativeSignature());
        if (!$request->hasValidRelativeSignature()) {
            return response()->json('Firma inválida en la petición', 400);
        } */

        try {
            $user->active = 1;
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
            $user->save();
        } catch (\Exception $e) {
            Log::error('Error al verificar el correo electrónico: ' . $e->getMessage());
            return response()->json('Error al verificar el correo electrónico', 500);
        }

        Log::info('Email verificado');
        return response()->json('Email verificado, ya puede iniciar sesión en el Sistema', 200);
    }

    function resend(Request $request)
    {

        $user = User::find($request->id);

        $user->sendEmailVerificationNotification();

        return response()->json('Re-envío de verificación de cuenta exitosa.', 200);
    }
}
