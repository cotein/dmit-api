<?php

namespace App\Listeners;

use App\Models\SaleInvoices;
use App\Events\CreatedInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Transformers\SaleInvoiceTransformer;
use App\Src\Repositories\SaleInvoiceRepository;

class SaveInvoice
{
    protected $saleInvoiceRepository;
    /**
     * Create the event listener.
     */
    public function __construct(SaleInvoiceRepository $saleInvoiceRepository)
    {
        $this->saleInvoiceRepository = $saleInvoiceRepository;
    }

    /**
     * Handle the event.
     */
    public function handle(CreatedInvoice $event): array
    {
        $data = $event->invoiceData;

        $invoice = $this->saleInvoiceRepository->store($data);

        $invoice = fractal($invoice, new SaleInvoiceTransformer())->toArray()['data'];

        return $invoice;
    }
}
