<?php

namespace App\Src\Services\Account;

use App\Mail\PasswordResetCodeMail;
use App\Models\AccountCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emisión y validación de los códigos de un solo uso de la cuenta.
 *
 * Reglas (acordadas con el dueño del sistema):
 *  - 6 dígitos, guardados hasheados, vencen a los 15 minutos;
 *  - máximo 5 intentos por código;
 *  - un solo uso: al usarse queda con `used_at` y no sirve más;
 *  - pedir un código nuevo invalida el anterior (si no quedan varios vivos a la vez);
 *  - reenviar tiene una espera mínima de 60 segundos;
 *  - pedir un código NUNCA revela si el correo existe: si no hay usuario no se manda
 *    nada y hacia afuera la respuesta es idéntica.
 */
class AccountCodeService
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public const RESEND_WAIT_SECONDS = 60;

    /**
     * Genera y manda un código. No devuelve nada a propósito: el controller
     * responde siempre lo mismo, exista o no el correo.
     */
    public function request(string $email, string $purpose, ?string $ip = null): void
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Se registra para poder ver intentos sobre mails inexistentes, pero
            // hacia afuera no cambia nada (anti-enumeración).
            Log::info('Cuenta: se pidió un código para un correo no registrado', [
                'purpose' => $purpose,
                'ip' => $ip,
            ]);

            return;
        }

        $last = $this->latestFor($email, $purpose);

        // Antispam: si acaba de pedir uno, no se genera otro ni se manda otro mail.
        if ($last && $last->created_at->gt(now()->subSeconds(self::RESEND_WAIT_SECONDS))) {
            return;
        }

        $this->invalidateActive($email, $purpose);

        $code = $this->newCode();

        AccountCode::create([
            'user_id' => $user->id,
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'requested_ip' => $ip,
        ]);

        $this->send($user, $code, $purpose);
    }

    /**
     * Valida el código. Devuelve el registro cuando está todo bien (para poder
     * consumirlo) o null cuando no sirve. Cada intento fallido suma.
     */
    public function verify(string $email, string $code, string $purpose): ?AccountCode
    {
        $record = $this->latestFor($email, $purpose);

        if (! $record || ! $record->isUsable()) {
            return null;
        }

        if ($record->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            return null;
        }

        return $record;
    }

    /** Marca el código como usado: no sirve para un segundo intento. */
    public function consume(AccountCode $record): void
    {
        $record->update(['used_at' => now()]);
    }

    /** El último código pedido para ese mail y ese fin (usado o no). */
    private function latestFor(string $email, string $purpose): ?AccountCode
    {
        return AccountCode::where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();
    }

    /** Deja sin efecto los códigos anteriores que todavía estaban vigentes. */
    private function invalidateActive(string $email, string $purpose): void
    {
        AccountCode::where('email', $email)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function newCode(): string
    {
        // random_int es criptográficamente seguro (rand/mt_rand no lo son).
        return (string) random_int(100000, 999999);
    }

    /**
     * El mail va con el mailer de Laravel (SMTP configurado por .env) y no con el
     * microservicio de emails: ese servicio quedó sin dominio propio y era el único
     * canal, así que ningún mail de cuenta podía salir.
     */
    private function send(User $user, string $code, string $purpose): void
    {
        $mailable = match ($purpose) {
            AccountCode::PURPOSE_PASSWORD_RESET => new PasswordResetCodeMail($user, $code),
            default => null,
        };

        if (! $mailable) {
            Log::warning('Cuenta: no hay mail definido para este tipo de código', ['purpose' => $purpose]);

            return;
        }

        try {
            Mail::to($user->email)->send($mailable);
        } catch (\Throwable $e) {
            // A propósito no se propaga: el código ya quedó generado y la respuesta
            // al usuario tiene que ser la misma exista o no el correo, haya salido
            // el mail o no. El problema real queda en el log.
            Log::error('Cuenta: no se pudo enviar el código por mail', [
                'user_id' => $user->id,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
