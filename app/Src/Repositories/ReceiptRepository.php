<?php

namespace App\Src\Repositories;

use App\Models\Receipt;
use App\Src\Constantes;
use App\Models\SaleInvoices;
use App\Models\ReceiptPayment;
use Jenssegers\Date\Date;

class ReceiptRepository
{
    protected $customer_cuenta_corriente_repository;

    public function __construct(CustomerCuentaCorrienteRepository $customer_cuenta_corriente_repository)
    {
        $this->customer_cuenta_corriente_repository = $customer_cuenta_corriente_repository;
    }

    public function store(SaleInvoices $si): void
    {
        if ($si->sales_condition_id === Constantes::CONTADO) {
            $receipt = new Receipt;

            $receipt->customer_id = $si->customer_id;
            $receipt->company_id = $si->company_id;
            $receipt->user_id = $si->user_id;
            $receipt->total = $si->items->sum('total');
            $receipt->save();

            $receipt->invoices()->attach($si->id);

            $receipt_payment = new ReceiptPayment;
            $receipt_payment->receipt_id = $receipt->id;
            $receipt_payment->payment_type_id = $si->payment_type_id;
            $receipt_payment->date = new Date();
            $receipt_payment->total = $receipt->total;
            $receipt_payment->save();

            $this->customer_cuenta_corriente_repository->store($receipt);
        }
    }
}
