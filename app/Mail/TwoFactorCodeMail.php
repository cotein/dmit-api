<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El código de 6 dígitos del segundo factor cuando el método elegido es el correo.
 */
class TwoFactorCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código de verificación · DMIT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.twoFactorCode.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
