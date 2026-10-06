<?php

namespace App\Src\Arba\Cot\Transport;

/**
 * Respuesta cruda de una llamada HTTP a ARBA.
 *
 * Separar "el transporte falló" de "ARBA contestó un error" no es un detalle de
 * estilo: si se corta la conexión, no se sabe si ARBA llegó a procesar el
 * archivo. Si ARBA contestó, aunque sea con un rechazo, se sabe que NO lo
 * procesó (o que ya lo había procesado). Las dos situaciones se reintentan
 * distinto, así que no pueden compartir un solo booleano.
 */
final class CotHttpResponse
{
    public function __construct(
        public readonly ?int $status = null,
        public readonly string $cuerpo = '',
        public readonly ?string $errorTransporte = null,
    ) {
    }

    /**
     * Se llegó a hablar con ARBA y contestó 2xx.
     */
    public function fueExitosa(): bool
    {
        return $this->errorTransporte === null
            && $this->status !== null
            && $this->status >= 200
            && $this->status < 300;
    }

    /**
     * No se pudo completar la llamada: timeout, DNS, TLS, conexión rechazada.
     * Es el único caso en el que queda la duda de si ARBA procesó el archivo.
     */
    public function falloElTransporte(): bool
    {
        return $this->errorTransporte !== null;
    }

    public function resumen(): string
    {
        if ($this->falloElTransporte()) {
            return 'Fallo de transporte: ' . $this->errorTransporte;
        }

        return 'HTTP ' . $this->status;
    }
}
