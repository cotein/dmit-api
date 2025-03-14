<?php

namespace App\Listeners;

use App\Events\SavedInvoice;
use App\Events\CreatedInvoice;
use App\Src\Repositories\ReceiptRepository;
use App\Transformers\SaleInvoiceTransformer;
use App\Src\Repositories\SaleInvoiceRepository;
use Illuminate\Support\Facades\Log;

class SaveInvoice
{
    protected $saleInvoiceRepository;

    protected $receiptRepository;
    /**
     * Create the event listener.
     */
    public function __construct(SaleInvoiceRepository $saleInvoiceRepository, ReceiptRepository $receiptRepository)
    {
        $this->saleInvoiceRepository = $saleInvoiceRepository;

        $this->receiptRepository = $receiptRepository;
    }

    /**
     * Handle the event.
     */
    public function handle(CreatedInvoice $event)
    {
        $data = $event->invoiceData;

        $invoice = $this->saleInvoiceRepository->store($data);

        $transformedInvoice = fractal($invoice, new SaleInvoiceTransformer())->toArray()['data'];

        $this->receiptRepository->store($invoice);

        SavedInvoice::dispatch($transformedInvoice);

        return $transformedInvoice;
    }
}
