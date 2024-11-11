<?php

namespace App\Src\Strategy\InvoiceStatus;

class InvoiceStatusContext
{
    private $strategy;

    public function __construct(InvoiceStatusStrategyInterface $strategy)
    {
        $this->strategy = $strategy;
    }

    public function executeStrategy($data, $invoice): int
    {
        return $this->strategy->setStatus($data, $invoice);
    }
}
