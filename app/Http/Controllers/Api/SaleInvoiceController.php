<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Transformers\SaleInvoiceTransformer;
use App\Src\Repositories\SaleInvoiceRepository;
use App\Transformers\SaleInvoiceCommentsTransformer;
use App\Transformers\SaleInvoicePrintTransformer;
use App\Transformers\SaleInvoiceReceiptTransformer;
use App\Transformers\SaleInvoiceWithPreviousPayments;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{
    protected $saleInvoiceRepository;

    public function __construct(SaleInvoiceRepository $saleInvoiceRepository)
    {
        $this->saleInvoiceRepository = $saleInvoiceRepository;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $invoices = $this->saleInvoiceRepository->find($request);

        if ($request->has('getPaymentOnReceipt')) {

            $invoices = fractal($invoices, new SaleInvoiceReceiptTransformer())->toArray()['data'];

            return response()->json($invoices, 200);
        }

        if ($request->has('print') && $request->get('print') === 'yes') {

            $invoices = fractal($invoices, new SaleInvoicePrintTransformer())->toArray()['data'];

            return response()->json($invoices, 200);
        }

        if ($request->has('invoice_id')) {

            $invoices = fractal($invoices, new SaleInvoiceWithPreviousPayments())->toArray()['data'];

            return response()->json($invoices, 200);
        }

        if ($request->has('comments')) {

            $data = fractal($invoices, new SaleInvoiceCommentsTransformer())->toArray()['data'];

            $pagination = [
                'total' => $invoices->total(),
                'per_page' => $invoices->perPage(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem()
            ];

            $response = [
                'pagination' => $pagination,
                'data' => $data
            ];

            return response()->json($response, 200);
        }

        if (!$request->has('print')) {
            $invoices = fractal($invoices, new SaleInvoiceTransformer())->toArray()['data'];

            return response()->json($invoices, 200);
        }

        $data = fractal($invoices, new SaleInvoiceTransformer())->toArray()['data'];

        $pagination = [
            'total' => $invoices->total(),
            'per_page' => $invoices->perPage(),
            'current_page' => $invoices->currentPage(),
            'last_page' => $invoices->lastPage(),
            'from' => $invoices->firstItem(),
            'to' => $invoices->lastItem()
        ];

        $response = [
            'pagination' => $pagination,
            'data' => $data
        ];

        return response()->json($response, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
