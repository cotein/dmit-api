<?php

namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class PasswordResetController extends Controller
{
    public function passwordResetCode(Request $request)
    {
        // Validar el correo electrónico
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe ser una dirección de correo válida.',
            'email.exists' => 'Éste correo electrónico no existe en nuestros registros.',
        ]);

        $email = $request->email;

        $token = Str::random(131); // Token aleatorio

        Cache::put('password_reset_token_' . $token, $email, now()->addMinutes(10));

        try {
            // Enviar el correo electrónico con el código
            $client = new \GuzzleHttp\Client();

            // Hacer la solicitud de manera asíncrona
            $promise = $client->postAsync('https://emailsender.dmit.ar/api/email-sender/user-forgot-password', [
                'json' => [
                    'email' => $email,
                    'token' => $token, // Asegúrate de enviar el código generado
                ],
            ]);

            // Manejar la respuesta de la promesa
            $promise->then(
                function ($response) {
                    // Verificar si la respuesta es exitosa
                    try {
                        if ($response->getStatusCode() === 200) {
                            $responseBody = json_decode($response->getBody(), true);
                            if (isset($responseBody['error'])) {
                                throw new \Exception('Error en el servicio de envío de correo: ' . $responseBody['error']['message']);
                            } else {
                                Log::info('Correo enviado correctamente: ' . $response->getBody());
                            }
                        } else {
                            throw new \Exception('Error en el servicio de envío de correo: ' . $response->getBody());
                        }

                        return response()->json([
                            'message' => 'ok',
                        ], 200);
                    } catch (\Exception $e) {
                        return response()->json([
                            'message' => 'error',
                            'error' => $e->getMessage(),
                        ], 500);
                    }
                },
                function ($exception) {
                    // Manejar errores de la solicitud
                    throw new \Exception('Error al enviar el correo de verificación: ' . $exception->getMessage());
                }
            );

            // Esperar a que la promesa se resuelva
            $promise->wait();

            return response()->json([
                'message' => 'ok',
            ], 200);
        } catch (\Exception $e) {
            // Manejar errores
            return response()->json([
                'message' => 'Ha ocurrido un error al enviar el correo: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
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
        $email = Cache::get('password_reset_token_' . $request->token);

        if (!$email) {
            return response()->json(['error' => 'Token inválido o expirado'], 400);
        }

        // Buscar al usuario por su correo electrónico
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['error' => 'Usuario no encontrado'], 404);
        }

        // Actualizar la contraseña del usuario
        $user->password = bcrypt($request->password);
        $user->save();

        // Eliminar el token de la caché
        Cache::forget('password_reset_token_' . $request->token);

        return response()->json(['message' => 'Contraseña actualizada correctamente'], 200);
    }
}
