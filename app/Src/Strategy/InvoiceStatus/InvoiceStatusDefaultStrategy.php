<?php

namespace App\Src\Strategy\InvoiceStatus;

use App\Models\SaleCondition;
use App\Src\Constantes;

class InvoiceStatusDefaultStrategy implements InvoiceStatusStrategyInterface
{
    public function setStatus($data, $invoice = null): int
    {
        $saleCondition_id = (int) $data['saleCondition'];

        $saleCondition = SaleCondition::find($saleCondition_id);

        if ($saleCondition->days === 0) {
            return Constantes::CANCELADA;
        }

        return Constantes::ADEUDADA;
    }
}
