<?php

namespace App\Src\InvoicesSale;

use App\Src\InvoicesSale\INVOICES_CONST;

class InvoiceFactory
{
    /**
     * Method createInvoice
     *
     * @param $invoice_type integer 1=FACTURA - 2=NOTA_DEBITO - 3=NOTA_CREDITO
     * @param $inscription_company integer Inscripción de la compañía en AFIP
     * 1-RESPONSABLE INSCRIPTO | 6-CONSUMIDOR FINAL
     * @param $inscription_customer integer Inscripción deL cliente en AFIP
     *
     * @return class InvoiceCreator
     */
    public static function createInvoice($invoice_type, $inscription_company, $inscription_customer)
    {
        $class = collect(INVOICES_CONST::INVOICES_CREATORS)->map(function ($invoice) use ($invoice_type, $inscription_company, $inscription_customer) {

            if ($invoice['invoice_type'] == $invoice_type && $invoice['inscription_company'] == $inscription_company && $invoice['inscription_customer'] == $inscription_customer) {
                return INVOICES_CONST::PATH_TO_INVOICES_CREATORS . '\\' . $invoice['class'];
            }
        })->filter()->values()->first();

        return new $class;
    }
}
