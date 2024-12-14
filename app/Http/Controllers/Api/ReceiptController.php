<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Transformers\ReceiptTransformer;
use App\Src\Repositories\ReceiptRepository;
use Illuminate\Support\Facades\Log;

class ReceiptController extends Controller
{

    private $receiptRepository;

    public function __construct(ReceiptRepository $receiptRepository)
    {
        $this->receiptRepository = $receiptRepository;
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
