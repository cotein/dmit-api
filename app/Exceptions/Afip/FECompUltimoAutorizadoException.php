<?php

namespace App\Exceptions\Afip;

/**
 * ARCA contestó con error al pedir el último comprobante autorizado. Hereda de AfipException
 * para que su mensaje llegue al frontend en lugar de un genérico "Server Error".
 */
class FECompUltimoAutorizadoException extends AfipException
{
    public function __construct($mensaje, $codigo)
    {
        parent::__construct((string) $mensaje, (int) $codigo);
    }
}
