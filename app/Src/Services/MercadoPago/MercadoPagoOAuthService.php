<?php

namespace App\Src\Services\MercadoPago;

use App\Models\MercadoPagoAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Conexión de la cuenta de Mercado Pago de cada empresa (modelo marketplace).
 *
 * El flujo es: la app arma la URL de autorización con un `state` firmado, el
 * dueño autoriza en Mercado Pago, MP vuelve al callback con un `code`, y acá se
 * cambia ese código por los tokens de ESA empresa.
 *
 * El `state` va firmado (HMAC con APP_KEY) y con vencimiento: es lo que impide
 * que alguien conecte su cuenta a la empresa de otro mandando un callback
 * inventado con company_id ajeno.
 */
class MercadoPagoOAuthService
{
    private const STATE_TTL_MINUTES = 10;

    public function __construct(private MercadoPagoClient $client)
    {
    }

    /**
     * URL a la que se manda al dueño de la empresa para que autorice.
     *
     * @return array{url: string, state: string}
     */
    public function authorizationUrl(int $companyId, int $userId): array
    {
        $clientId = config('mercadopago.client_id');

        if (empty($clientId)) {
            throw new RuntimeException('Falta MERCADOPAGO_CLIENT_ID: sin eso no se puede conectar una cuenta.');
        }

        $state = $this->signState($companyId, $userId);

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => config('mercadopago.oauth.redirect_url'),
            'state' => $state,
        ]);

        return [
            'url' => config('mercadopago.oauth.authorize_url') . '?' . $query,
            'state' => $state,
        ];
    }

    /**
     * Valida el state y devuelve [company_id, user_id]. Tira excepción si la
     * firma no cierra o si pasó el tiempo: nunca se confía en el state pelado.
     *
     * @return array{company_id: int, user_id: int}
     */
    public function verifyState(string $state): array
    {
        $partes = explode('.', $state);

        if (count($partes) !== 2) {
            throw new RuntimeException('El state de OAuth es inválido.');
        }

        [$payload, $firma] = $partes;
        $esperada = hash_hmac('sha256', $payload, config('app.key'));

        if (! hash_equals($esperada, $firma)) {
            throw new RuntimeException('La firma del state de OAuth no coincide.');
        }

        $datos = json_decode(base64_decode($payload), true);

        if (! is_array($datos) || ! isset($datos['company_id'], $datos['user_id'], $datos['ts'])) {
            throw new RuntimeException('El state de OAuth está incompleto.');
        }

        $edad = now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($datos['ts']), true);

        if ($edad > self::STATE_TTL_MINUTES) {
            throw new RuntimeException('El state de OAuth venció: volvé a iniciar la conexión.');
        }

        return ['company_id' => (int) $datos['company_id'], 'user_id' => (int) $datos['user_id']];
    }

    /**
     * Canjea el `code` por los tokens de la empresa y deja la cuenta conectada.
     */
    public function connect(int $companyId, string $code): MercadoPagoAccount
    {
        $tokens = $this->client->postAsApp('oauth/token', [
            'client_id' => config('mercadopago.client_id'),
            'client_secret' => config('mercadopago.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('mercadopago.oauth.redirect_url'),
        ]);

        if (empty($tokens['access_token'])) {
            throw new RuntimeException('Mercado Pago no devolvió access_token al conectar la cuenta.');
        }

        // Los datos de la cuenta se piden aparte: el token ya es de la empresa.
        $perfil = [];
        try {
            $perfil = $this->client->withToken($tokens['access_token'])->get('users/me');
        } catch (\Throwable $e) {
            // No es fatal: la conexión sirve igual sin el nickname.
            Log::warning('Mercado Pago: no se pudo leer users/me al conectar', ['error' => $e->getMessage()]);
        }

        $account = MercadoPagoAccount::withoutGlobalScope(\App\Src\Scopes\CompanyScope::class)
            ->firstOrNew(['company_id' => $companyId]);

        $account->fill([
            'mp_user_id' => isset($perfil['id']) ? (string) $perfil['id'] : null,
            'nickname' => $perfil['nickname'] ?? null,
            'email' => $perfil['email'] ?? null,
            'public_key' => $tokens['public_key'] ?? null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_expires_at' => ! empty($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'scopes' => $tokens['scope'] ?? null,
            'status' => MercadoPagoAccount::STATUS_CONNECTED,
            'connected_at' => now(),
            'last_refreshed_at' => now(),
            'last_error' => null,
        ]);
        $account->save();

        return $account;
    }

    /**
     * Renueva el token cuando está por vencer. Si MP lo rechaza, la cuenta queda
     * en estado 'error' y el módulo lo dice en pantalla en vez de fallar callado.
     */
    public function refresh(MercadoPagoAccount $account): MercadoPagoAccount
    {
        if (empty($account->refresh_token)) {
            $account->update([
                'status' => MercadoPagoAccount::STATUS_ERROR,
                'last_error' => 'La cuenta no tiene refresh_token: hay que volver a conectarla.',
            ]);

            return $account;
        }

        try {
            $tokens = $this->client->postAsApp('oauth/token', [
                'client_id' => config('mercadopago.client_id'),
                'client_secret' => config('mercadopago.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
            ]);
        } catch (\Throwable $e) {
            $account->update([
                'status' => MercadoPagoAccount::STATUS_ERROR,
                'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }

        $account->update([
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? $account->refresh_token,
            'token_expires_at' => ! empty($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'status' => MercadoPagoAccount::STATUS_CONNECTED,
            'last_refreshed_at' => now(),
            'last_error' => null,
        ]);

        return $account;
    }

    /**
     * Token utilizable para operar: renueva si está por vencer.
     */
    public function freshToken(MercadoPagoAccount $account): string
    {
        if ($account->tokenExpiresSoon((int) config('mercadopago.token_refresh_margin_minutes'))) {
            $account = $this->refresh($account);
        }

        if (empty($account->access_token)) {
            throw new RuntimeException('La empresa no tiene una cuenta de Mercado Pago conectada.');
        }

        return $account->access_token;
    }

    private function signState(int $companyId, int $userId): string
    {
        $payload = base64_encode(json_encode([
            'company_id' => $companyId,
            'user_id' => $userId,
            'ts' => now()->timestamp,
            'nonce' => Str::random(16),
        ]));

        return $payload . '.' . hash_hmac('sha256', $payload, config('app.key'));
    }
}
