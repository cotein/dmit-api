<?php

declare(strict_types=1);

namespace App\Exceptions\Afip;

use App\Src\Afip\AfipWsdl;
use Throwable;

/**
 * El WSDL que usa el paquete cotein/api-afip no declara el campo obligatorio de la RG 5616.
 * Si esto se dispara, la factura NO se puede emitir: conviene fallar acá, con este mensaje,
 * en lugar de mandar el comprobante a ARCA y comerse un 10245 sin saber por qué.
 */
class AfipWsdlOutdatedException extends AfipException
{
    protected int $status = 503;

    public function __construct(string $environment, string $path, ?Throwable $previous = null)
    {
        $mensaje = sprintf(
            'El WSDL de %s (%s) no declara <%s> (RG 5616). El SoapClient de PHP descarta los ' .
            'campos que no están en el WSDL, así que ARCA rechazaría el comprobante con 10245. ' .
            'Ejecutá "php artisan afip:sync-wsdl" en el servidor y reintentá.',
            strtoupper($environment),
            $path,
            AfipWsdl::REQUIRED_FIELD,
        );

        parent::__construct($mensaje, 0, $previous);
    }
}
