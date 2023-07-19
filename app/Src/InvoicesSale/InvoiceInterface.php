<?php

namespace App\Src\InvoicesSale;

interface InvoiceInterface
{

    /**
     * Method proccessData
     *
     * @param remito $items - Se le pasa un array con los items que deriban del remito creado,
     * éste método lo procesa y arma el array se luego se enviará a la AFIP para generar
     * la factura electrónica
     *
     * @return array
     */
    public function processItems(): array;
}
