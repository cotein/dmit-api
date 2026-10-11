<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail de verificación de la cuenta (el que sale al registrarse y al pedir el
 * reenvío).
 *
 * Antes esto se mandaba al microservicio de emails, que hace tiempo quedó sin
 * dominio y no entregaba nada: los usuarios se registraban y no les llegaba nunca
 * el mail para activar la cuenta. Ahora sale por el mailer de Laravel (Resend),
 * igual que el resto de los mails de cuenta.
 *
 * El enlace apunta a la pantalla del facturador (/verify-email?token=…), que es la
 * que después llama al API para validar el token.
 */
class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $url;

    public function __construct(public object $user, public string $token)
    {
        $this->url = rtrim((string) config('app.frontend_url'), '/') . '/verify-email?token=' . urlencode($token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activá tu cuenta de DMIT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verifyEmail.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
