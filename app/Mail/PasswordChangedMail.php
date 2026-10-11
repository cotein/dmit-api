<?php

namespace App\Mail;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de que la contraseña cambió.
 *
 * Es la parte que avisa cuando el cambio NO lo hizo el dueño de la cuenta: sin
 * este mail, un cambio hecho con un correo comprometido pasa desapercibido.
 */
class PasswordChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Carbon $changedAt;

    public function __construct(public User $user, ?Carbon $changedAt = null)
    {
        $this->changedAt = $changedAt ?? now();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu contraseña cambió · DMIT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.passwordChanged.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
