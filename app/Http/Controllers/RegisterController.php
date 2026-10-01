<?php

namespace App\Http\Controllers;

use App\Jobs\SendVerificationEmailJob;
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
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{

    public function register(RegisterUserFormRequest $request): JsonResponse
    {
        // El Form Request ya validó los datos de entrada
        $validatedData = $request->validated();

        try {
            // Usar una transacción de DB es una excelente práctica. ¡Bien hecho!
            $user = DB::transaction(function () use ($validatedData, $request) {

                // Las reglas del FormRequest son anidadas (user.*), así que validated()
                // devuelve ['user' => [...]]: leer las claves planas rompía el registro
                // con "Undefined array key name".
                $userData = $validatedData['user'];

                $user = User::create([
                    'name' => $userData['name'],
                    'last_name' => $userData['lastName'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'type_user_id' => $this->determineUserType($userData['email']),
                ]);

                // Generar token para la verificación de email
                $token = Str::random(100);
                Cache::put('verification_token_' . $token, $user->id, now()->addHours(1));

                // Despachar el Job para que se ejecute en segundo plano
                SendVerificationEmailJob::dispatch($user->email, $user->name, $token)->afterCommit();

                activity()
                    ->causedBy($user)
                    ->performedOn($user)
                    ->log('Usuario creado');

                return $user;
            });

            return response()->json([
                'message' => 'Usuario creado satisfactoriamente. Por favor, verifica tu correo electrónico.',
                'data' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            // Loguear el error real
            Log::error('Fallo en el registro de usuario: ' . $e->getMessage());

            // Devolver una respuesta de error genérica al cliente
            return response()->json([
                'message' => 'Ocurrió un error inesperado al procesar el registro.'
            ], 500);
        }
    }

    private function determineUserType(string $email): int
    {
        $rootEmails = [
            'diego.barrueta@gmail.com',
            'marcelo.j.callo@gmail.com',
            'marcelo.callao@piamondsa.com.ar'
        ];

        if (in_array($email, $rootEmails)) {
            return Constantes::USER_ROOT;
        }

        return Constantes::USER_ADMIN;
    }

    public function checkCuit()
    {

        $wspuc13 = AfipWebService::findWebService('padron', 'production', null);

        return response()->json($wspuc13, 200);
    }
}
