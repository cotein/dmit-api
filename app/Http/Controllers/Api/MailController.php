<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envío de comprobantes por correo.
 *
 * Antes el frontend le pegaba directo al microservicio de emails, que quedó sin
 * dominio: el botón "Enviar" del comprobante no mandaba nada. Ahora pasa por acá y
 * sale por el mailer de Laravel (Resend), con el PDF que manda el navegador adjunto.
 */
class MailController extends Controller
{
    public function sendInvoice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'subject' => 'required|string|max:200',
            'html' => 'required|string',
            'attachments' => 'nullable|array',
            'attachments.0.filename' => 'nullable|string|max:160',
            'attachments.0.content' => 'nullable|string',
            'from' => 'nullable|string',
        ], [
            'to.required' => 'Falta el destinatario.',
            'subject.required' => 'Falta el asunto.',
            'html.required' => 'Falta el cuerpo del mensaje.',
        ]);

        // El destinatario puede venir separado por comas o punto y coma.
        $destinatarios = $this->emails($data['to']);

        if ($destinatarios === []) {
            return response()->json(['message' => 'El destinatario no es un correo válido.'], 422);
        }

        $adjunto = $data['attachments'][0] ?? null;

        try {
            $mailable = new InvoiceMail(
                subjectLine: $data['subject'],
                htmlBody: $data['html'],
                attachmentContent: $adjunto['content'] ?? null,
                attachmentName: $adjunto['filename'] ?? null,
                replyToAddress: $data['from'] ?? null,
            );

            $mensaje = Mail::to($destinatarios);

            if (! empty($data['cc'])) {
                $mensaje->cc($this->emails($data['cc']));
            }

            if (! empty($data['bcc'])) {
                $mensaje->bcc($this->emails($data['bcc']));
            }

            $mensaje->send($mailable);
        } catch (Throwable $e) {
            Log::error('Comprobante: no se pudo enviar el correo', [
                'to' => $data['to'],
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'No pudimos enviar el correo. Probá de nuevo en un momento.'], 500);
        }

        Log::info('Comprobante enviado por correo', [
            'to' => $destinatarios,
            'adjunto' => $adjunto['filename'] ?? null,
            'user_id' => $request->user()?->id,
        ]);

        return response()->json(['message' => 'Correo enviado correctamente.']);
    }

    /**
     * Normaliza lo que el usuario escribió en el campo (puede ser "a@b.com",
     * "a@b.com, c@d.com" o separado por ";").
     *
     * @return array<int, string>
     */
    private function emails(?string $valor): array
    {
        $partes = preg_split('/[,;]+/', (string) $valor) ?: [];

        $validos = [];

        foreach ($partes as $parte) {
            $email = trim($parte);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $validos[] = $email;
            }
        }

        return $validos;
    }
}
