<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Src\Constantes;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Events\RegisteredUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Transformers\UserTransformer;
use Carbon\Carbon;
use Throwable;

class AuthController extends Controller
{
    public function login(): Response
    {
        $data = request()->validate([
            'email' => 'email|required',
            'password' => 'required'
        ]);

        if (!auth()->attempt($data)) {
            return response(['message' => 'Credenciales incorrectas'], 400);
        }

        if (!auth()->user()->isActive()) {
            return response(['message' => 'Usuario no activado'], 400);
        }

        $token = auth()->user()->createToken('API DMIT')->accessToken;

        $user = fractal(auth()->user(), new UserTransformer())->toArray()['data'];

        return response(['user' => $user, 'token' => $token]);
    }

    /**
     * Crea el usuario a partir del payload YA validado por Google.
     */
    private function createUserFromVerifiedPayload(array $payload): User
    {
        $user = new User;
        $user->name = strtoupper($payload['given_name'] ?? $payload['name'] ?? '');
        $user->last_name = strtoupper($payload['family_name'] ?? '');
        $user->email = $payload['email'];
        $user->type_user_id = Constantes::USER_ADMIN;
        $user->google_id = $payload['sub'];
        $user->active = true;
        $user->email_verified_at = Carbon::now();
        $user->save();

        event(new RegisteredUser($user));

        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->log('Usuario creado');

        return $user;
    }

    private function generateTokenForUser($user)
    {
        $tokenResult = $user->createToken('API DMIT');
        $token = $tokenResult->accessToken;
        $expiresAt = $tokenResult->token->expires_at;

        $userToken = [
            'access_token' => $token,
            'expires_in' => $expiresAt->diffInSeconds(Carbon::now()),
            'refresh_token' => $tokenResult->token->id, // Assuming you have a refresh token mechanism
            'token_type' => 'Bearer',
        ];

        $userData = fractal($user, new UserTransformer())->toArray()['data'];

        return response(['user' => $userData, 'userToken' => $userToken]);
    }

    /**
     * Login con Google Identity Services.
     *
     * El cliente manda el id_token (el "credential" que devuelve Google). El backend
     * lo valida contra Google y recién entonces emite el token de la API.
     * NUNCA confiar en el email/sub que llegue en el body: cualquiera podría pedir
     * un token para la cuenta de otra persona.
     */
    public function googleLogin(): Response
    {
        $data = request()->validate([
            'id_token' => 'required_without:credential|string',
            'credential' => 'required_without:id_token|string',
        ]);

        $idToken = $data['id_token'] ?? $data['credential'];

        try {
            $payload = $this->verifiedGooglePayload($idToken);
        } catch (Throwable $e) {
            Log::warning('googleLogin: id_token rechazado', ['error' => $e->getMessage()]);

            return response(['message' => 'No se pudo validar la sesión de Google'], 400);
        }

        $user = User::where('google_id', $payload['sub'])->first()
            ?? User::where('email', $payload['email'])->first();

        if ($user) {
            if (is_null($user->google_id)) {
                $user->google_id = $payload['sub'];
                $user->save();
            }

            if (! $user->isActive()) {
                return response(['message' => 'Usuario no activado'], 400);
            }
        } else {
            $user = $this->createUserFromVerifiedPayload($payload);
        }

        return $this->generateTokenForUser($user);
    }

    /**
     * Valida el id_token contra el endpoint tokeninfo de Google y devuelve el payload confiable.
     *
     * @throws \RuntimeException cuando el token no es válido para esta aplicación
     */
    private function verifiedGooglePayload(string $idToken): array
    {
        $response = Http::timeout(5)
            ->acceptJson()
            ->get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken]);

        if (! $response->ok()) {
            throw new \RuntimeException('tokeninfo respondió '.$response->status());
        }

        $payload = $response->json();

        $clientId = config('services.google.client_id');

        if (! $clientId) {
            throw new \RuntimeException('GOOGLE_CLIENT_ID no configurado');
        }

        if (($payload['aud'] ?? null) !== $clientId) {
            throw new \RuntimeException('aud no coincide con GOOGLE_CLIENT_ID');
        }

        if (! in_array($payload['iss'] ?? '', ['https://accounts.google.com', 'accounts.google.com'], true)) {
            throw new \RuntimeException('iss inesperado');
        }

        if (($payload['email_verified'] ?? 'false') !== 'true') {
            throw new \RuntimeException('email no verificado por Google');
        }

        if (empty($payload['sub']) || empty($payload['email'])) {
            throw new \RuntimeException('payload de Google incompleto');
        }

        return $payload;
    }
}
