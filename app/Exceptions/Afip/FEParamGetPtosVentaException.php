<?php

namespace App\Exceptions\Afip;

use Exception;

class FEParamGetPtosVentaException extends Exception
{
    public function __construct($mensaje, $codigo)
    {
        parent::__construct($mensaje, $codigo);
    }
}
