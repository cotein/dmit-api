<?php

namespace App\Src\Strategy\InvoiceStatus;

interface InvoiceStatusStrategyInterface
{
    public function setStatus($data, $invoice = null): int;
}
