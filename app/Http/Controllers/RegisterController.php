<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Src\Constantes;
use Illuminate\Support\Str;
use App\Events\RegisteredUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Cotein\ApiAfip\Facades\AfipWebService;
use App\Http\Requests\RegisterUserFormRequest;

class RegisterController extends Controller
{

    public function register(RegisterUserFormRequest $request): \Illuminate\Http\JsonResponse
    {
        /* header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization'); */

        DB::beginTransaction();

        try {
            $user = new User;

            $user->name = strtoupper($request->user['name']);
            $user->last_name = strtoupper($request->user['lastName']);
            $user->email = $request->user['email'];
            $user->password = Hash::make($request->user['password']);
            if ($request->user['email'] === 'diego.barrueta@gmail.com' || $request->user['email'] === 'marcelo.j.callo@gmail.com' || $request->user['email'] === 'marcelo.callao@piamondsa.com.ar') {
                $user->type_user_id = Constantes::USER_ROOT;
            } else {
                $user->type_user_id = Constantes::USER_ADMIN;
            }

            $user->save();

            // Generar un token único
            $token = Str::random(100);
            // Guardar el token en la caché con una expiración de 1 hora
            Cache::put('verification_token_' . $token, $user->id, now()->addHours(1));

            $client = new \GuzzleHttp\Client();

            // Hacer la solicitud de manera asíncrona
            $promise = $client->postAsync('http://dmit_email_sender_app:3000/api/email-sender/user-email-verification', [
                'json' => [
                    'to' => $request->user['email'],
                    'name' => $request->user['name'],
                    'token' => $token,
                ],
            ]);

            // Manejar la respuesta de la promesa
            $promise->then(
                function ($response) use ($user) {
                    // Verificar si la respuesta es exitosa
                    if ($response->getStatusCode() === 201) {
                        Log::info('Correo enviado correctamente: ' . $response->getBody());
                    } else {
                        throw new \Exception('Error en el servicio de envío de correo: ' . $response->getBody());
                    }
                },
                function ($exception) {
                    // Manejar errores de la solicitud
                    Log::error('Error en la solicitud HTTP: ' . $exception->getMessage());
                    throw new \Exception('Error al enviar el correo de verificación: ' . $exception->getMessage());
                }
            );

            // Esperar a que la promesa se resuelva
            $promise->wait();

            activity()
                ->causedBy($user)
                ->withProperties($request->all())
                ->performedOn($user)
                ->log('Usuario creado');

            DB::commit();

            return response()->json([
                'name' => $user->name,
                'message' => 'Usuario creado satisfactoriamente, ahora debe verificar su correo electrónico.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            activity()
                ->causedBy($user ?? null)
                ->performedOn($user ?? null)
                ->withProperties(['exception' => $e])
                ->log('Error al crear usuario');

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkCuit()
    {

        $wspuc13 = AfipWebService::findWebService('padron', 'production', null);

        return response()->json($wspuc13, 200);
    }
}
