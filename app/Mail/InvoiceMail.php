<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Comprobante por correo: el cuerpo lo escribe el usuario en el editor del facturador
 * y el PDF del comprobante viaja adjunto (el frontend lo manda en base64, igual que
 * antes, así no hace falta que el API salga a buscar el archivo).
 *
 * Antes esto lo armaba el microservicio de emails, que ya no existe.
 */
class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $htmlBody,
        public ?string $attachmentContent = null,
        public ?string $attachmentName = null,
        public ?string $replyToAddress = null,
    ) {
    }

    public function envelope(): Envelope
    {
        // El "responder a" va al correo de la empresa, así si el cliente contesta le
        // llega a quien corresponde y no al remitente técnico del envío.
        $replyTo = $this->replyToAddress && filter_var($this->replyToAddress, FILTER_VALIDATE_EMAIL)
            ? [new Address($this->replyToAddress)]
            : [];

        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice.content',
        );
    }

    public function attachments(): array
    {
        if (! $this->attachmentContent || ! $this->attachmentName) {
            return [];
        }

        // El frontend manda base64 (a veces con el prefijo "data:application/pdf;base64,").
        $limpio = preg_replace('#^data:[^;,]+;base64,#', '', $this->attachmentContent) ?? '';
        $binario = base64_decode($limpio, true);

        if ($binario === false) {
            return [];
        }

        return [
            Attachment::fromData(fn () => $binario, $this->attachmentName)->withMime('application/pdf'),
        ];
    }
}
