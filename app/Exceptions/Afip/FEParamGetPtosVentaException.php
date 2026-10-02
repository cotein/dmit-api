<?php

namespace App\Exceptions\Afip;

/**
 * ARCA contestó con error al consultar los puntos de venta. Hereda de AfipException para que
 * su mensaje llegue al frontend en lugar de un genérico "Server Error".
 */
class FEParamGetPtosVentaException extends AfipException
{
    public function __construct($mensaje, $codigo)
    {
        parent::__construct((string) $mensaje, (int) $codigo);
    }
}
