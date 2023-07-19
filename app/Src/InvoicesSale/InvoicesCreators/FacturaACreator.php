<?php

namespace App\Src\InvoicesSale\InvoicesCreators;

use App\Src\InvoicesSale\Invoices\FacturaA;

class FacturaACreator
{
    public function createInvoice()
    {
        return new FacturaA();
    }
}
