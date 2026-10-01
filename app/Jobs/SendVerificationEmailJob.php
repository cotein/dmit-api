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
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post('http://dmit_email_sender_app:3000/api/email-sender/user-email-verification', [
                'json' => [
                    'to' => $this->email,
                    'name' => $this->name,
                    'token' => $this->token,
                ],
            ]);

            if ($response->getStatusCode() !== 201) {
                Log::error('Error en el servicio de envío de correo: ' . $response->getBody());
            } else {
                Log::info('Correo de verificación despachado para: ' . $this->email);
            }
        } catch (\Exception $e) {
            Log::error('Fallo el job SendVerificationEmailJob: ' . $e->getMessage());
        }
    }
}
