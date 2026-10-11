<?php

namespace App\Jobs;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mail de activación de la cuenta (se dispara al registrarse).
 *
 * Antes le pegaba por HTTP al microservicio de emails, que quedó sin dominio: nadie
 * recibía el mail de activación y las cuentas quedaban sin verificar. Ahora sale por
 * el mailer de Laravel (Resend), igual que el resto de los mails de cuenta.
 */
class SendVerificationEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $email,
        public string $name,
        public string $token
    ) {}

    public function handle(): void
    {
        try {
            // El mail muestra el nombre: se usa el del usuario real si existe.
            $user = User::where('email', $this->email)->first() ?? (object) ['name' => $this->name];

            Mail::to($this->email)->send(new VerifyEmailMail($user, $this->token));

            Log::info('Correo de verificación enviado para: ' . $this->email);
        } catch (Throwable $e) {
            // Un mail que no sale no puede tumbar el registro del usuario.
            Log::error('Fallo el envío del correo de verificación: ' . $e->getMessage(), [
                'email' => $this->email,
            ]);
        }
    }
}
