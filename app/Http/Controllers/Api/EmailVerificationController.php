<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Auth\Events\Verified;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class EmailVerificationController extends Controller
{

    public function verify(Request $request, $id)
    {
        $user = User::find($id);

        $user->active = 1;

        $user->save();

        if ($user->email_verified_at) {
            return response()->json('Cuenta ya verificada, inicie sesión en el Sistema', 200);
        }

        if (!$request->hasValidSignature()) {
            return response()->json('Debe reenviar la petición de verificación de cuenta', 200);
        }

        if (!hash_equals((string) $request->all()['hash'], sha1($user->getEmailForVerification()))) {
            return response()->json('Debe reenviar la petición de verificación de cuenta', 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json('Email verificado, ya puede iniciar sesión en el Sistema', 200);
    }

    function resend(Request $request)
    {

        $user = User::find($request->id);

        $user->sendEmailVerificationNotification();

        return response()->json('Re-envío de verificación de cuenta exitosa.', 200);
    }
}
