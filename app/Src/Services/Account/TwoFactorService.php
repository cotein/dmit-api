<?php

namespace App\Src\Services\Account;

use App\Models\AccountCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Doble factor de la cuenta, opcional, con dos métodos a elección del usuario.
 *
 *  - `totp`: app autenticadora. El secreto se guarda cifrado y queda "pendiente"
 *    hasta que el usuario confirma con un código (así no se queda afuera por no
 *    haber terminado de escanear el QR).
 *  - `email`: código de 6 dígitos al correo, con la misma tabla y las mismas reglas
 *    que el restablecimiento de contraseña (15 min, 5 intentos, un solo uso).
 *
 * En los dos casos valen los códigos de recuperación: 10 códigos de un solo uso que
 * se muestran UNA vez y quedan hasheados. Sirven para entrar si se pierde el teléfono.
 */
class TwoFactorService
{
    public const METHOD_TOTP = 'totp';

    public const METHOD_EMAIL = 'email';

    public const RECOVERY_CODES = 10;

    public function __construct(private TotpService $totp, private AccountCodeService $codes)
    {
    }

    public function isEnabled(User $user): bool
    {
        return $user->two_factor_confirmed_at !== null && in_array($user->two_factor_method, [self::METHOD_TOTP, self::METHOD_EMAIL], true);
    }

    /** Lo que la pantalla de seguridad necesita saber para dibujarse. */
    public function state(User $user): array
    {
        return [
            'enabled' => $this->isEnabled($user),
            'method' => $user->two_factor_method,
            'confirmed_at' => optional($user->two_factor_confirmed_at)->toDateTimeString(),
            'pending' => ! $this->isEnabled($user) && ! empty($user->two_factor_secret),
            'recovery_codes_left' => count($this->recoveryCodeHashes($user)),
            'methods' => [
                ['value' => self::METHOD_TOTP, 'label' => 'App autenticadora'],
                ['value' => self::METHOD_EMAIL, 'label' => 'Código al correo'],
            ],
        ];
    }

    /**
     * Primer paso del método TOTP: genera el secreto y devuelve la URL del QR.
     * Todavía NO queda activo: falta el código de confirmación.
     */
    public function startTotp(User $user): array
    {
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_method' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => $this->totp->otpauthUrl($user->email, $secret),
        ];
    }

    /**
     * Confirma el alta del TOTP con un código de la app. Recién acá queda activo y
     * se entregan (una sola vez) los códigos de recuperación.
     */
    public function confirmTotp(User $user, string $code): array
    {
        if (empty($user->two_factor_secret)) {
            throw new RuntimeException('No hay un secreto pendiente: empezá de nuevo el alta.');
        }

        if (! $this->totp->verify($user->two_factor_secret, $code)) {
            throw new RuntimeException('El código no es válido. Fijate que el reloj del teléfono esté en hora.');
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_method' => self::METHOD_TOTP,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $this->hashCodes($codes),
        ])->save();

        return ['recovery_codes' => $codes];
    }

    /** El método por correo no necesita alta: se activa y listo. */
    public function enableEmail(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_method' => self::METHOD_EMAIL,
            'two_factor_confirmed_at' => now(),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => $this->hashCodes($codes),
        ])->save();

        return ['recovery_codes' => $codes];
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_method' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
    }

    /** Vuelve a emitir los 10 códigos (los anteriores dejan de servir). */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (! $this->isEnabled($user)) {
            throw new RuntimeException('El doble factor no está activado.');
        }

        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $this->hashCodes($codes),
        ])->save();

        return ['recovery_codes' => $codes];
    }

    /** Manda el código de 6 dígitos al correo (método 'email'). */
    public function sendChallengeCode(User $user, ?string $ip = null): void
    {
        $this->codes->request($user->email, AccountCode::PURPOSE_TWO_FACTOR_EMAIL, $ip);
    }

    /**
     * Valida el código del segundo factor.
     *
     * Devuelve con qué pasó ('totp', 'email' o 'recovery') o null si no sirve. El
     * código de recuperación se consume: no se puede usar dos veces.
     */
    public function verify(User $user, string $code): ?string
    {
        if (! $this->isEnabled($user)) {
            return null;
        }

        if ($this->consumeRecoveryCode($user, $code)) {
            return 'recovery';
        }

        if ($user->two_factor_method === self::METHOD_TOTP) {
            return $this->totp->verify((string) $user->two_factor_secret, $code) ? 'totp' : null;
        }

        $registro = $this->codes->verify($user->email, $code, AccountCode::PURPOSE_TWO_FACTOR_EMAIL);

        if (! $registro) {
            return null;
        }

        $this->codes->consume($registro);

        return 'email';
    }

    /** Los códigos de recuperación se comparan normalizados (sin guiones, en mayúsculas). */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $normalizado = $this->normalizeCode($code);

        if (strlen($normalizado) !== 8) {
            return false;
        }

        $hashes = $this->recoveryCodeHashes($user);

        foreach ($hashes as $indice => $hash) {
            if (Hash::check($normalizado, $hash)) {
                unset($hashes[$indice]);

                $user->forceFill(['two_factor_recovery_codes' => json_encode(array_values($hashes))])->save();

                return true;
            }
        }

        return false;
    }

    private function recoveryCodeHashes(User $user): array
    {
        if (empty($user->two_factor_recovery_codes)) {
            return [];
        }

        $decodificado = json_decode((string) $user->two_factor_recovery_codes, true);

        return is_array($decodificado) ? $decodificado : [];
    }

    /**
     * Guarda los códigos hasheados y NORMALIZADOS (sin guiones): el usuario los puede
     * escribir con guion (como se los mostramos) o sin guion, y los dos tienen que
     * entrar. Hashear la versión con guion y comparar la sin guion fue un bug real:
     * ningún código de recuperación servía.
     */
    private function hashCodes(array $codigos): string
    {
        return json_encode(array_map(fn ($c) => Hash::make($this->normalizeCode($c)), $codigos));
    }

    private function generateRecoveryCodes(): array
    {
        $codigos = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $codigos[] = $this->formatCode();
        }

        return $codigos;
    }

    /** Código de 8 caracteres en dos grupos: A1B2-C3D4. */
    private function formatCode(): string
    {
        $limpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', Str::random(24)) ?? '');
        $limpio = substr($limpio, 0, 8);

        return substr($limpio, 0, 4) . '-' . substr($limpio, 4, 4);
    }

    private function normalizeCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
    }
}
