<?php

namespace App\Exceptions\Afip;

use Exception;

class FECompUltimoAutorizadoException extends Exception
{
    public function __construct($mensaje, $codigo)
    {
        parent::__construct($mensaje, $codigo);
    }
}
