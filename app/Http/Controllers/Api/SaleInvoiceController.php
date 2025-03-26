<?php

namespace App\Http\Controllers\Api;

use App\Models\SaleInvoices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Transformers\SaleInvoiceTransformer;
use App\Src\Repositories\SaleInvoiceRepository;
use App\Transformers\SaleInvoicePrintTransformer;
use App\Transformers\SaleInvoiceLastMonthInvoiced;
use App\Transformers\SaleInvoiceReceiptTransformer;
use App\Transformers\SaleInvoiceCommentsTransformer;
use App\Transformers\SaleInvoiceWithPreviousPayments;

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
        Log::info('Invoice Request:', $request->all());

        $invoices = $this->saleInvoiceRepository->find($request);
        Log::debug('Datos obtenidos:', [
            'total' => $invoices->count() ?? 0,
            'ejemplo' => $invoices->first() ?? null
        ]);

        // Reemplazo del match con switch tradicional
        if ($request->has('getLastMonthInvoiced') || $request->has('getDailySalesReport')) {
            $response = $this->basicResponse($invoices);
        } elseif ($request->has('getPaymentOnReceipt')) {
            $response = $this->transformedResponse($invoices, new SaleInvoiceReceiptTransformer());
        } elseif ($request->has('print') && $request->get('print') === 'yes') {
            $response = $this->transformedResponse($invoices, new SaleInvoicePrintTransformer());
        } elseif ($request->has('invoice_id')) {
            $response = $this->transformedResponse($invoices, new SaleInvoiceWithPreviousPayments());
        } elseif ($request->has('comments')) {
            $response = $this->paginatedResponse($invoices, new SaleInvoiceCommentsTransformer());
        } else {
            $response = $this->paginatedResponse($invoices, new SaleInvoiceTransformer());
        }

        Log::debug('Respuesta final:', is_array($response) ? $response : ['data' => $response]);
        return response()->json($response, 200);
    }

    // Métodos auxiliares (se mantienen igual)
    private function basicResponse($data)
    {
        return $data;
    }

    private function transformedResponse($data, $transformer)
    {
        return fractal($data, $transformer)->toArray()['data'];
    }

    private function paginatedResponse($data, $transformer)
    {
        $transformed = fractal($data, $transformer)->toArray();

        return [
            'pagination' => [
                'total' => $data->total(),
                'per_page' => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem()
            ],
            'data' => $transformed['data']
        ];
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
