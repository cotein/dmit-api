<?php

namespace App\Src\Services\MercadoLibre;

use App\Models\MercadoLibreAccount;
use App\Src\Scopes\CompanyScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Conexión de la cuenta de MercadoLibre de cada empresa.
 *
 * El flujo es el mismo que el de Mercado Pago: la app arma la URL de
 * autorización con un `state` firmado, el vendedor autoriza en MercadoLibre,
 * MercadoLibre vuelve al callback con un `code`, y acá se cambia ese código por
 * los tokens de ESA empresa.
 *
 * El `state` va firmado (HMAC con APP_KEY) y con vencimiento: es lo que impide
 * que alguien conecte su cuenta a la empresa de otro mandando un callback
 * inventado con company_id ajeno.
 *
 * PKCE: el `code_verifier` NO viaja en el state (el state va y vuelve por el
 * navegador). Se guarda del lado del servidor atado al nonce del state y se
 * consume en el callback.
 */
class MercadoLibreOAuthService
{
    private const CACHE_PKCE_PREFIX = 'mercadolibre:pkce:';

    public function __construct(private MercadoLibreClient $client)
    {
    }

    /**
     * URL a la que se manda al vendedor para que autorice.
     *
     * @return array{url: string, state: string}
     */
    public function authorizationUrl(int $companyId, int $userId): array
    {
        $clientId = config('mercadolibre.app_id');
        $redirectUrl = config('mercadolibre.oauth.redirect_url');

        if (empty($clientId) || empty($redirectUrl)) {
            throw new RuntimeException(
                'Falta configurar MERCADOLIBRE_APP_ID y MERCADOLIBRE_REDIRECT_URL: sin eso no se puede conectar una cuenta.',
            );
        }

        [$state, $nonce] = $this->signState($companyId, $userId);

        $query = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUrl,
            'state' => $state,
        ];

        if (config('mercadolibre.oauth.pkce')) {
            $verifier = Str::random(64);

            // El verificador queda del lado del servidor: al navegador sólo va el desafío.
            Cache::put(
                self::CACHE_PKCE_PREFIX . $nonce,
                $verifier,
                now()->addMinutes((int) config('mercadolibre.oauth.state_ttl_minutes', 10)),
            );

            $query['code_challenge'] = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
            $query['code_challenge_method'] = 'S256';
        }

        return [
            'url' => config('mercadolibre.oauth.authorize_url') . '?' . http_build_query($query),
            'state' => $state,
        ];
    }

    /**
     * Valida el state y devuelve la empresa, el usuario y el nonce.
     * Tira excepción si la firma no cierra o si pasó el tiempo.
     *
     * @return array{company_id: int, user_id: int, nonce: string}
     */
    public function verifyState(string $state): array
    {
        $partes = explode('.', $state);

        if (count($partes) !== 2) {
            throw new RuntimeException('El state de OAuth es inválido.');
        }

        [$payload, $firma] = $partes;
        $esperada = hash_hmac('sha256', $payload, (string) config('app.key'));

        if (! hash_equals($esperada, $firma)) {
            throw new RuntimeException('La firma del state de OAuth no coincide.');
        }

        $datos = json_decode((string) base64_decode($payload, true), true);

        if (! is_array($datos) || ! isset($datos['company_id'], $datos['user_id'], $datos['ts'], $datos['nonce'])) {
            throw new RuntimeException('El state de OAuth está incompleto.');
        }

        $edad = now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($datos['ts']), true);

        if ($edad > (int) config('mercadolibre.oauth.state_ttl_minutes', 10)) {
            throw new RuntimeException('El state de OAuth venció: volvé a iniciar la conexión.');
        }

        return [
            'company_id' => (int) $datos['company_id'],
            'user_id' => (int) $datos['user_id'],
            'nonce' => (string) $datos['nonce'],
        ];
    }

    /**
     * Canjea el `code` por los tokens de la empresa y deja la cuenta conectada.
     */
    public function connect(int $companyId, string $state, string $code): MercadoLibreAccount
    {
        $nonce = $this->verifyState($state)['nonce'];

        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => config('mercadolibre.app_id'),
            'client_secret' => config('mercadolibre.client_secret'),
            'code' => $code,
            'redirect_uri' => config('mercadolibre.oauth.redirect_url'),
        ];

        $verifier = Cache::pull(self::CACHE_PKCE_PREFIX . $nonce);

        if (config('mercadolibre.oauth.pkce')) {
            if (empty($verifier)) {
                throw new RuntimeException('Se perdió el code_verifier de PKCE: volvé a iniciar la conexión.');
            }

            $payload['code_verifier'] = $verifier;
        }

        $tokens = $this->client->withoutToken()->postForm('oauth/token', $payload);

        if (empty($tokens['access_token'])) {
            throw new RuntimeException('MercadoLibre no devolvió access_token al conectar la cuenta.');
        }

        // Los datos del vendedor se piden aparte: el token ya es de la empresa.
        $perfil = [];
        try {
            $perfil = $this->client->withToken($tokens['access_token'])->usersMe();
        } catch (\Throwable $e) {
            // No es fatal: la conexión sirve igual sin el nickname.
            Log::warning('MercadoLibre: no se pudo leer users/me al conectar', ['error' => $e->getMessage()]);
        }

        $etiquetas = $perfil['tags'] ?? [];

        $account = MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
            ->firstOrNew(['company_id' => $companyId]);

        $account->fill([
            'ml_user_id' => isset($perfil['id']) ? (int) $perfil['id'] : null,
            'site_id' => $perfil['site_id'] ?? config('mercadolibre.oauth.site', 'MLA'),
            'nickname' => $perfil['nickname'] ?? null,
            'email' => $perfil['email'] ?? null,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'token_expires_at' => ! empty($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
            'scopes' => $tokens['scope'] ?? null,
            'status' => MercadoLibreAccount::STATUS_CONNECTED,
            'connected_at' => now(),
            'last_refreshed_at' => now(),
            'last_error' => null,
            // Vendedores con este tag publican con el modelo nuevo (User Products):
            // el alta clásica con variaciones ya no les funciona.
            'user_product_seller' => is_array($etiquetas) && in_array('user_product_seller', $etiquetas, true),
            'raw' => $perfil,
        ]);
        $account->save();

        return $account;
    }

    /**
     * Renueva el access token.
     *
     * El refresh token es de UN SOLO USO: cada refresh devuelve uno nuevo y el
     * anterior queda inválido. Por eso la fila se bloquea y se vuelve a leer
     * dentro de la transacción: si otro proceso (un webhook, el comando, una
     * pantalla) renovó mientras esperábamos, usamos ese token y no quemamos la
     * cadena. Sin esto, dos llamadas simultáneas dejan la cuenta pidiendo
     * reconexión a mano.
     */
    public function refresh(MercadoLibreAccount $account): MercadoLibreAccount
    {
        $margen = (int) config('mercadolibre.token_refresh_margin_minutes', 60);

        return DB::transaction(function () use ($account, $margen) {
            $bloqueada = MercadoLibreAccount::withoutGlobalScope(CompanyScope::class)
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->first();

            if ($bloqueada === null) {
                throw new RuntimeException('La cuenta de MercadoLibre ya no existe.');
            }

            // Otro proceso renovó mientras esperábamos el lock.
            if (! $bloqueada->tokenExpiresSoon($margen)) {
                return $bloqueada;
            }

            if (empty($bloqueada->refresh_token)) {
                $bloqueada->update([
                    'status' => MercadoLibreAccount::STATUS_ERROR,
                    'last_error' => 'La cuenta no tiene refresh_token: hay que volver a conectarla.',
                ]);

                throw new RuntimeException('La cuenta de MercadoLibre no tiene refresh_token: hay que reconectar.');
            }

            try {
                $tokens = $this->client->withoutToken()->postForm('oauth/token', [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('mercadolibre.app_id'),
                    'client_secret' => config('mercadolibre.client_secret'),
                    'refresh_token' => $bloqueada->refresh_token,
                ]);
            } catch (\Throwable $e) {
                $bloqueada->update([
                    'status' => MercadoLibreAccount::STATUS_ERROR,
                    'last_error' => mb_substr($e->getMessage(), 0, 1000),
                ]);

                throw $e;
            }

            $bloqueada->update([
                'access_token' => $tokens['access_token'],
                // Siempre se guarda el refresh token nuevo: el anterior ya no sirve.
                'refresh_token' => $tokens['refresh_token'] ?? $bloqueada->refresh_token,
                'token_expires_at' => ! empty($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                'status' => MercadoLibreAccount::STATUS_CONNECTED,
                'last_refreshed_at' => now(),
                'last_error' => null,
            ]);

            return $bloqueada->refresh();
        });
    }

    /**
     * Token utilizable para operar: renueva si está por vencer.
     */
    public function freshToken(MercadoLibreAccount $account): string
    {
        $margen = (int) config('mercadolibre.token_refresh_margin_minutes', 60);

        if ($account->tokenExpiresSoon($margen)) {
            $account = $this->refresh($account);
        }

        if (empty($account->access_token)) {
            throw new RuntimeException('La empresa no tiene una cuenta de MercadoLibre conectada.');
        }

        return $account->access_token;
    }

    /**
     * Desconecta la cuenta: le avisa a MercadoLibre que ya no la usamos y deja
     * la cuenta revocada sin tokens. Se conservan las publicaciones ya
     * importadas (el usuario las puede seguir viendo y volver a conectar).
     */
    public function disconnect(MercadoLibreAccount $account): MercadoLibreAccount
    {
        if ($account->isConnected() && ! empty($account->ml_user_id)) {
            try {
                $this->client->withToken($this->freshToken($account))->revoke((int) $account->ml_user_id);
            } catch (\Throwable $e) {
                // Si MercadoLibre no responde igual la desconectamos de nuestro
                // lado: el usuario pidió desconectar, no que le confirmemos.
                Log::warning('MercadoLibre: no se pudo revocar el permiso al desconectar', [
                    'company_id' => $account->company_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $account->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => MercadoLibreAccount::STATUS_REVOKED,
            'last_error' => null,
        ]);

        return $account;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function signState(int $companyId, int $userId): array
    {
        $nonce = Str::random(16);

        $payload = base64_encode(json_encode([
            'company_id' => $companyId,
            'user_id' => $userId,
            'ts' => now()->timestamp,
            'nonce' => $nonce,
        ]));

        return [$payload . '.' . hash_hmac('sha256', $payload, (string) config('app.key')), $nonce];
    }
}
