<?php

namespace App\Src\Strategy\InvoiceStatus;

use App\Models\SaleInvoices;
use App\Src\Constantes;
use Illuminate\Support\Collection;

class InvoiceStatusNotaCreditoStrategy implements InvoiceStatusStrategyInterface
{
    public function setStatus($data, $invoice = null): int
    {
        if ($invoice === null) {
            return Constantes::ADEUDADA;
        }

        $cbteAsoc = SaleInvoices::where('cbte_desde', $data['FECAEDetRequest']['CbtesAsoc'][0]['Nro'])->get()->first();

        $products = collect($data['products']);

        if ($this->isSameAmount($cbteAsoc, $products) && $cbteAsoc->status_id === Constantes::CANCELADA) {

            return Constantes::ADEUDADA; //a favor del cliente
        }

        if ($this->isSameAmount($cbteAsoc, $products) && $cbteAsoc->status_id === Constantes::ADEUDADA) {

            $cbteAsoc->status_id = Constantes::CANCELADA;
            $cbteAsoc->save();
            return Constantes::CANCELADA;
        }

        if (! $this->isSameAmount($cbteAsoc, $products) && $cbteAsoc->status_id === Constantes::CANCELADA) {

            return Constantes::ADEUDADA; //a favor del cliente
        }

        if (! $this->isSameAmount($cbteAsoc, $products) && $cbteAsoc->status_id === Constantes::ADEUDADA) {

            return Constantes::ADEUDADA;
        }

        return Constantes::ADEUDADA;
    }

    private function isSameAmount(SaleInvoices $invoice, Collection $products): bool
    {
        $importToNotaCredito = $products->reduce(function ($carry, $product) {
            return $carry + $product['neto_import'] + $product['iva_import'] + $product['percep_iibb_import'] + $product['percep_iva_import'];
        }, 0);

        return $importToNotaCredito === $invoice->totalInvoiced();
    }
}
