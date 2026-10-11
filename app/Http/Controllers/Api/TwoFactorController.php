<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Services\Account\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gestión del doble factor desde la cuenta (el usuario ya está logueado).
 *
 * Activar o desactivar el segundo factor, y regenerar los códigos de recuperación,
 * exigen la CONTRASEÑA: son operaciones que puede disparar alguien que se sentó en
 * una máquina ya abierta, así que se pide de nuevo.
 */
class TwoFactorController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor)
    {
    }

    public function state(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->twoFactor->state($request->user())]);
    }

    /** Paso 1 del método TOTP: devuelve el secreto y la URL del QR (todavía sin activar). */
    public function setupTotp(Request $request): JsonResponse
    {
        $this->validarPassword($request);

        return response()->json(['data' => $this->twoFactor->startTotp($request->user())]);
    }

    /** Paso 2: confirma con un código de la app y entrega los códigos de recuperación. */
    public function confirmTotp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'Ingresá el código que muestra la app.',
        ]);

        try {
            $resultado = $this->twoFactor->confirmTotp($request->user(), $data['code']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Log::info('Cuenta: doble factor activado por TOTP', ['user_id' => $request->user()->id]);

        return response()->json([
            'message' => 'Listo: el doble factor quedó activado con tu app autenticadora.',
            'data' => $resultado,
        ]);
    }

    /** El método por correo se activa directo (el código llega al iniciar sesión). */
    public function enableEmail(Request $request): JsonResponse
    {
        $this->validarPassword($request);

        $resultado = $this->twoFactor->enableEmail($request->user());

        Log::info('Cuenta: doble factor activado por correo', ['user_id' => $request->user()->id]);

        return response()->json([
            'message' => 'Listo: cada vez que entres te vamos a mandar un código al correo.',
            'data' => $resultado,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $this->validarPassword($request);

        $this->twoFactor->disable($request->user());

        Log::info('Cuenta: doble factor desactivado', ['user_id' => $request->user()->id]);

        return response()->json(['message' => 'El doble factor quedó desactivado.']);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $this->validarPassword($request);

        try {
            $resultado = $this->twoFactor->regenerateRecoveryCodes($request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Estos son los códigos nuevos: los anteriores ya no sirven.',
            'data' => $resultado,
        ]);
    }

    /** La contraseña se pide de nuevo: estas acciones cambian cómo se entra a la cuenta. */
    private function validarPassword(Request $request): void
    {
        $data = $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Confirmá con tu contraseña.',
        ]);

        $user = $request->user();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            abort(response()->json(['message' => 'La contraseña no es correcta.'], 422));
        }
    }
}
