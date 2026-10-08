<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Transformers\ReceiptTransformer;
use App\Src\Repositories\ReceiptRepository;
use App\Src\Services\ReceiptCancellationService;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{

    private $receiptRepository;

    private $receiptCancellationService;

    public function __construct(
        ReceiptRepository $receiptRepository,
        ReceiptCancellationService $receiptCancellationService
    ) {
        $this->receiptRepository = $receiptRepository;
        $this->receiptCancellationService = $receiptCancellationService;
    }

    /**
     * Anula un recibo. No se borra: queda marcado con motivo, fecha y usuario,
     * y se revierte la imputación sobre los comprobantes.
     */
    public function anular(Request $request, int $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $receipt = Receipt::findOrFail($id);

        // El recibo tiene que ser de una empresa del usuario. Se responde 404 y
        // no 403 para no confirmar la existencia de recibos ajenos.
        $companyIds = $request->user()
            ? array_map('intval', $request->user()->companies()->pluck('companies.id')->all())
            : [];

        if (! in_array((int) $receipt->company_id, $companyIds, true)) {
            abort(404);
        }

        try {
            $receipt = $this->receiptCancellationService->cancel(
                $receipt,
                $request->input('reason'),
                $request->user() ? (int) $request->user()->id : null
            );
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        $receipt = fractal($receipt, new ReceiptTransformer())->toArray()['data'];

        return response()->json($receipt, 200);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $receiptList = $this->receiptRepository->find($request);

        $receipts = fractal($receiptList, new ReceiptTransformer())->toArray()['data'];

        $pagination = [
            'total' => $receiptList->total(),
            'per_page' => $receiptList->perPage(),
            'current_page' => $receiptList->currentPage(),
            'last_page' => $receiptList->lastPage(),
            'from' => $receiptList->firstItem(),
            'to' => $receiptList->lastItem()
        ];

        $response = [
            'pagination' => $pagination,
            'data' => $receipts
        ];

        return response()->json($response, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $receipt = $this->receiptRepository->createReceipt($request);

        $receipt = fractal($receipt, new ReceiptTransformer())->toArray()['data'];

        return response()->json($receipt, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $receipt = $this->receiptRepository->show($id);

        $receipt = fractal($receipt, new ReceiptTransformer())->toArray()['data'];

        return response()->json($receipt, 200);
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
