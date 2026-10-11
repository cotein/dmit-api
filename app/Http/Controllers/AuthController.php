<?php

namespace App\Http\Controllers;

use App\Models\AccountCode;
use App\Models\User;
use App\Src\Constantes;
use App\Src\Services\Account\AccountCodeService;
use App\Src\Services\Account\LoginChallengeService;
use App\Src\Services\Account\TwoFactorService;
use Illuminate\Http\Response;
use App\Events\RegisteredUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Transformers\UserTransformer;
use Carbon\Carbon;
use Throwable;

/**
 * Login de la aplicación.
 *
 * Hay TRES caminos y los tres terminan igual (o devuelven el token de la API, o
 * piden el segundo factor si el usuario lo tiene activado):
 *  1. usuario y contraseña  → POST /login
 *  2. Google                → POST /auth/google
 *  3. un código al correo   → POST /auth/login-code/request + /auth/login-code/verify
 *
 * El token se emite con Passport (`createToken`), igual que en el login de Google
 * desde siempre: la forma de la respuesta —{user, userToken}— es la que el frontend
 * ya sabe consumir.
 */
class AuthController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactor,
        private LoginChallengeService $challenges,
        private AccountCodeService $codes,
    ) {
    }

    /**
     * Usuario y contraseña. Si tiene doble factor, NO devuelve token: devuelve el
     * challenge que se canjea con el código en `twoFactorChallenge()`.
     *
     * Las credenciales se validan a mano (Hash::check) y no con `auth()->attempt()`:
     * `attempt()` usa el guard por DEFECTO y en este API eso puede ser el guard de
     * Passport, que no tiene `attempt` (daba 500: "Method TokenGuard::attempt does
     * not exist"). Además así el login no toca la sesión, que es lo correcto en un
     * API que autentica con Bearer.
     */
    public function login(): Response
    {
        $data = request()->validate([
            'email' => 'email|required',
            'password' => 'required'
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], (string) $user->password)) {
            Log::info('Login rechazado: credenciales incorrectas', ['email' => $data['email'], 'ip' => request()->ip()]);

            return response(['message' => 'Credenciales incorrectas'], 400);
        }

        if (! $user->isActive()) {
            return response(['message' => 'Usuario no activado'], 400);
        }

        if ($this->twoFactor->isEnabled($user)) {
            return $this->challengeResponse($user);
        }

        return $this->generateTokenForUser($user);
    }

    /**
     * Segundo factor: canjea el challenge + el código por el token de la API.
     *
     * Acepta el código de la app autenticadora, el que llegó por correo (según el
     * método elegido) y siempre los códigos de recuperación.
     */
    public function twoFactorChallenge(): Response
    {
        $data = request()->validate([
            'challenge' => 'required|string',
            'code' => 'required|string',
        ], [
            'challenge.required' => 'Falta el identificador de la verificación.',
            'code.required' => 'Ingresá el código.',
        ]);

        $user = $this->challenges->consume($data['challenge']);

        if (! $user) {
            return response(['message' => 'La verificación venció. Volvé a ingresar.'], 422);
        }

        if (! $user->isActive()) {
            return response(['message' => 'Usuario no activado'], 400);
        }

        $metodo = $this->twoFactor->verify($user, $data['code']);

        if (! $metodo) {
            return response(['message' => 'El código no es válido o ya venció.'], 422);
        }

        $extra = ['two_factor_method' => $metodo];

        if ($metodo === 'recovery') {
            // Se avisa para que el usuario sepa cuántos códigos de recuperación le quedan.
            $extra['used_recovery_code'] = true;
            $extra['recovery_codes_left'] = $this->twoFactor->state($user)['recovery_codes_left'];
        }

        return $this->generateTokenForUser($user, $extra);
    }

    /** Pide un código para entrar sin contraseña. Respuesta única, exista o no el correo. */
    public function requestLoginCode(): Response
    {
        $data = request()->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
        ]);

        $this->codes->request($data['email'], AccountCode::PURPOSE_LOGIN_CODE, request()->ip());

        return response([
            'message' => 'Si el correo está registrado, te enviamos un código de 6 dígitos.',
        ]);
    }

    /** Canjea el código por el token (o pide el segundo factor si tiene doble factor). */
    public function verifyLoginCode(): Response
    {
        $data = request()->validate([
            'email' => 'required|email',
            'code' => 'required|digits:6',
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no es válido.',
            'code.required' => 'Ingresá el código.',
            'code.digits' => 'El código son 6 dígitos.',
        ]);

        $registro = $this->codes->verify($data['email'], $data['code'], AccountCode::PURPOSE_LOGIN_CODE);

        if (! $registro) {
            return response(['message' => 'El código es incorrecto o ya venció. Pedí uno nuevo.'], 422);
        }

        $user = User::find($registro->user_id);

        if (! $user) {
            return response(['message' => 'El código es incorrecto o ya venció. Pedí uno nuevo.'], 422);
        }

        if (! $user->isActive()) {
            return response(['message' => 'Usuario no activado'], 400);
        }

        $this->codes->consume($registro);

        // Entrar con un código no saltea el doble factor: si lo tiene, se pide igual.
        if ($this->twoFactor->isEnabled($user)) {
            return $this->challengeResponse($user);
        }

        return $this->generateTokenForUser($user);
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

    private function generateTokenForUser($user, array $extra = [])
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

        return response(array_merge(['user' => $userData, 'userToken' => $userToken], $extra));
    }

    /**
     * Respuesta del primer factor cuando el usuario tiene doble factor: no hay token
     * todavía, va el challenge. Si eligió el método por correo, se manda el código acá.
     */
    private function challengeResponse(User $user): Response
    {
        $metodo = $user->two_factor_method;

        if ($metodo === TwoFactorService::METHOD_EMAIL) {
            $this->twoFactor->sendChallengeCode($user, request()->ip());
        }

        return response([
            'two_factor_required' => true,
            'method' => $metodo,
            'challenge' => $this->challenges->issue($user),
            'message' => $metodo === TwoFactorService::METHOD_EMAIL
                ? 'Te enviamos un código de 6 dígitos al correo.'
                : 'Ingresá el código de tu app autenticadora.',
        ]);
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

        // Google es un primer factor: si la cuenta tiene doble factor, se pide igual.
        if ($this->twoFactor->isEnabled($user)) {
            return $this->challengeResponse($user);
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
