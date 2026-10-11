<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El enlace con el token para restablecer la contraseña (flujo viejo, que sigue vivo).
 *
 * Antes este mail lo armaba el microservicio de emails; ahora sale por el mailer de
 * Laravel para que el restablecimiento no dependa de un servicio que quedó caído.
 */
class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Restablecé tu contraseña · DMIT Facturador Online',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passwordLink.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
