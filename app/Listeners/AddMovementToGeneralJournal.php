<?php

namespace App\Listeners;

use Carbon\Carbon;
use App\Src\Constantes;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Src\Repositories\GeneralJournalRepository;

class AddMovementToGeneralJournal
{
    const CONTADO = 1;
    const CAJA_ID = 6;
    const VENTA_ID = 16;
    const DEUDORES_POR_VENTA_ID = 24;

    protected $generalJournalRepository;
    /**
     * Create the event listener.
     */
    public function __construct(GeneralJournalRepository $generalJournalRepository)
    {
        $this->generalJournalRepository = $generalJournalRepository;
    }

    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        $invoice = $event->invoiceData;
        $uuid = Str::uuid()->toString();
        $date = Carbon::now();

        $customer = "{$invoice['customer']['name']} {$invoice['customer']['last_name']}";
        $voucher = "{$invoice['voucher']['name']} {$invoice['voucher']['pto_vta']}-{$invoice['voucher']['cbte_desde']}";
        $description = "{$customer} {$voucher}";
        $total = $invoice['voucher']['total'];

        if (($invoice['company']['afipInscription'] === Constantes::RESPONSABLE_MONOTRIBUTO || $invoice['company']['afipInscription'] === Constantes::IVA_SUJETO_EXENTO)) {

            if ($invoice['voucher']['sale_conditions_id'] == self::CONTADO) {
                $this->generalJournalRepository->addDebito($uuid, $date, $description, self::VENTA_ID, $total, $invoice['company']['id']);
                $this->generalJournalRepository->addCredito($uuid, $date, 'CAJA', self::CAJA_ID, $total, $invoice['company']['id']);
            } else {
                $this->generalJournalRepository->addDebito($uuid, $date, $description, self::VENTA_ID, $total, $invoice['company']['id']);
                $this->generalJournalRepository->addCredito($uuid, $date, 'DEUDORES POR VENTAS', self::DEUDORES_POR_VENTA_ID, $total, $invoice['company']['id']);
            }
        }
        //TODO agregar el debito fical a responsable inscripto
        /* if (($invoice['company']['afipInscription'] === Constantes::IVA_RESPONSABLE_INSCRIPTO)) {

            if ($invoice['voucher']['sale_conditions_id'] == self::CONTADO) {
                $this->generalJournalRepository->addDebito($uuid, $date, $description, self::VENTA_ID, $total, $invoice['company']['id']);
                $this->generalJournalRepository->addCredito($uuid, $date, 'CAJA', self::CAJA_ID, $total, $invoice['company']['id']);
            } else {
                $this->generalJournalRepository->addDebito($uuid, $date, $description, self::VENTA_ID, $total, $invoice['company']['id']);
                $this->generalJournalRepository->addCredito($uuid, $date, 'DEUDORES POR VENTAS', self::DEUDORES_POR_VENTA_ID, $total, $invoice['company']['id']);
            }
        } */
    }
}
