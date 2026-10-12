<?php

namespace App\Src\Services\MercadoPago;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente HTTP de Mercado Pago.
 *
 * Es la única pieza que habla con la API. Se construye con UN access token:
 * el de una empresa (OAuth) o el de plataforma (suscripciones). Todo lo que no
 * sea eso (qué se cobra, a quién, cómo se imputa) vive en los servicios de
 * dominio, así el transporte se puede probar solo.
 */
class MercadoPagoClient
{
    public function __construct(private ?string $accessToken = null)
    {
    }

    public function withToken(string $accessToken): self
    {
        return new self($accessToken);
    }

    public function withoutToken(): self
    {
        return new self(null);
    }

    /**
     * POST autenticado con idempotency key.
     *
     * La idempotency key evita duplicar un cobro cuando el navegador reintenta
     * o cuando el usuario hace doble clic: MP devuelve la misma preferencia.
     */
    public function post(string $path, array $payload, ?string $idempotencyKey = null): array
    {
        return $this->send('post', $path, $payload, $idempotencyKey);
    }

    public function put(string $path, array $payload): array
    {
        return $this->send('put', $path, $payload);
    }

    public function delete(string $path, array $payload = []): array
    {
        return $this->send('delete', $path, $payload);
    }

    public function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($this->url($path), $query);
        } catch (\Throwable $e) {
            throw new MercadoPagoException('No se pudo contactar a Mercado Pago: ' . $e->getMessage(), 0, $e);
        }

        return $this->handle($response, $path);
    }

    /**
     * POST SIN token: es el intercambio de código OAuth y el refresh, que se
     * autentican con client_id + client_secret en el cuerpo.
     */
    public function postAsApp(string $path, array $payload): array
    {
        try {
            $response = Http::timeout(config('mercadopago.api.timeout'))->acceptJson()->post($this->url($path), $payload);
        } catch (\Throwable $e) {
            throw new MercadoPagoException('No se pudo contactar a Mercado Pago: ' . $e->getMessage(), 0, $e);
        }

        return $this->handle($response, $path);
    }

    private function send(string $method, string $path, array $payload, ?string $idempotencyKey = null): array
    {
        if (empty($this->accessToken)) {
            throw new MercadoPagoException('Falta el access token de Mercado Pago para llamar a ' . $path);
        }

        $pending = $this->request();

        if ($idempotencyKey !== null) {
            $pending = $pending->withHeaders(['X-Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = match ($method) {
                'put' => $pending->put($this->url($path), $payload),
                'delete' => $pending->delete($this->url($path), $payload),
                default => $pending->post($this->url($path), $payload),
            };
        } catch (\Throwable $e) {
            throw new MercadoPagoException('No se pudo contactar a Mercado Pago: ' . $e->getMessage(), 0, $e);
        }

        return $this->handle($response, $path);
    }

    private function request(): PendingRequest
    {
        $pending = Http::timeout(config('mercadopago.api.timeout'))
            ->acceptJson()
            ->asJson();

        return empty($this->accessToken) ? $pending : $pending->withToken($this->accessToken);
    }

    private function url(string $path): string
    {
        return rtrim(config('mercadopago.api.base_url'), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Traduce la respuesta de MP. El error de MP se propaga con su mensaje real
     * (cause) porque es lo único que explica un rechazo de credenciales.
     */
    private function handle($response, string $path): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $body = $response->json();

        if (is_array($body)) {
            $detalle = $body['message'] ?? ($body['error'] ?? json_encode($body));
            $causas = collect($body['cause'] ?? [])->pluck('description')->filter()->implode(' · ');

            $mensaje = trim('Mercado Pago respondió ' . $response->status() . ' en ' . $path . ': ' . $detalle . ($causas ? ' (' . $causas . ')' : ''));
        } else {
            $mensaje = 'Mercado Pago respondió ' . $response->status() . ' en ' . $path;
        }

        throw new MercadoPagoException($mensaje, $response->status());
    }
}
