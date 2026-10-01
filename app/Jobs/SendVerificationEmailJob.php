<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
        // La URL sale de config (env EMAIL_SENDER_URL): antes estaba hardcodeada
        // al host interno de Docker y el registro fallaba con cURL error 6.
        $baseUrl = rtrim((string) config('services.email_sender.url'), '/');

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $response = $client->post($baseUrl . '/api/email-sender/user-email-verification', [
                'json' => [
                    'to' => $this->email,
                    'name' => $this->name,
                    'token' => $this->token,
                ],
            ]);

            // El microservicio responde 200 cuando envía: comparar contra 201
            // generaba un log de error en cada envío exitoso.
            if ($response->getStatusCode() >= 400) {
                Log::error('Error en el servicio de envío de correo: ' . $response->getBody());
            } else {
                Log::info('Correo de verificación despachado para: ' . $this->email);
            }
        } catch (\Throwable $e) {
            Log::error('Fallo el job SendVerificationEmailJob: ' . $e->getMessage());
        }
    }
}
