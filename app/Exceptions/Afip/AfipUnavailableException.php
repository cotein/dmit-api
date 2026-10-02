<?php

declare(strict_types=1);

namespace App\Exceptions\Afip;

use Throwable;

/**
 * No se pudo hablar con ARCA (SOAP): timeout, TLS, servicio caído. Se devuelve un mensaje
 * legible en lugar del genérico "Server Error" que Laravel responde en producción.
 */
class AfipUnavailableException extends AfipException
{
    protected int $status = 503;

    public function __construct(string $mensaje, ?Throwable $previous = null)
    {
        parent::__construct('ARCA no respondió: ' . $mensaje, 0, $previous);
    }
}
