<?php

namespace App\Src\InvoicesSale\Invoices;

use App\Src\InvoicesSale\InvoiceInterface;

class FacturaA implements InvoiceInterface
{
    public function processItems(): array
    {
        return ['pp'];
    }
}
