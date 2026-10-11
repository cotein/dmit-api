<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * El código para entrar sin contraseña ("entrar con un código").
 */
class LoginCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $code)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu código para entrar a DMIT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loginCode.content',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
