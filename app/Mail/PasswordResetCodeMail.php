<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El código de 6 dígitos para restablecer la contraseña.
 *
 * OJO: acá el código viaja en claro (es lo que el usuario tiene que leer); lo que
 * se guarda hasheado es la copia de la base.
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código para restablecer la contraseña · DMIT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passwordCode.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
