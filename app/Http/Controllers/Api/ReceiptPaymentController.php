<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Src\Repositories\ReceiptPaymentRepository;
use App\Transformers\ReceiptPaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pagos recibidos: una fila por pago, con filtros de fecha, cliente, tipo de
 * pago, importe y estado derivado.
 *
 * El company_id llega en el request, como en el resto del circuito de recibos
 * (ver el trabajo diferido sobre multi-tenant en el plan del API).
 */
class ReceiptPaymentController extends Controller
{
    protected $receiptPaymentRepository;

    public function __construct(ReceiptPaymentRepository $receiptPaymentRepository)
    {
        $this->receiptPaymentRepository = $receiptPaymentRepository;
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => 'required|integer',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'customer_id' => 'nullable|integer',
            'payment_type_id' => 'nullable|integer',
            'amount_from' => 'nullable|numeric',
            'amount_to' => 'nullable|numeric|gte:amount_from',
            'status' => 'nullable|in:activo,anulado,a_cuenta,parcial,cancelado,imputado',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $payments = $this->receiptPaymentRepository->getPaginated($request);

        $rows = fractal($payments->getCollection(), new ReceiptPaymentTransformer())->toArray()['data'];

        return response()->json([
            'pagination' => [
                'total' => $payments->total(),
                'per_page' => $payments->perPage(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'data' => $rows,
        ], 200);
    }
}
