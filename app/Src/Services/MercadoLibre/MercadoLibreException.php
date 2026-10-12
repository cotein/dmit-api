<?php

namespace App\Src\Services\MercadoLibre;

use RuntimeException;

/**
 * Error de MercadoLibre con el contexto necesario para decidir qué hacer.
 *
 * Importa distinguir tres familias:
 *   - 401/403: el token dejó de servir (o la aplicación perdió permisos),
 *   - 429: nos pasamos de la cuota de la aplicación; hay que esperar,
 *   - 400: el pedido está mal armado (atributos obligatorios, precio con
 *     automatización activa, etc.) y reintentar no arregla nada.
 */
class MercadoLibreException extends RuntimeException
{
    public function __construct(
        string $message,
        private int $statusCode = 0,
        private array $payload = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    /** El token ya no sirve: hay que pedirle al vendedor que reconecte. */
    public function isUnauthorized(): bool
    {
        return in_array($this->statusCode, [401, 403], true);
    }

    /** El pedido está mal: reintentar no cambia el resultado. */
    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500 && ! $this->isRateLimited();
    }

    /**
     * Mensaje corto y accionable para mostrarle al usuario, sin el JSON crudo.
     */
    public function userMessage(): string
    {
        $causas = [];

        foreach (($this->payload['cause'] ?? []) as $causa) {
            if (is_array($causa) && ! empty($causa['message'])) {
                $causas[] = $causa['message'];
            }
        }

        $mensaje = $this->payload['message'] ?? $this->getMessage();

        if ($causas !== []) {
            $mensaje .= ': ' . implode(' | ', $causas);
        }

        return mb_substr((string) $mensaje, 0, 500);
    }
}
