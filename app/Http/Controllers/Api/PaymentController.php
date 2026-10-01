<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payment;
use App\Src\Repositories\PaymentRepository;
use App\Src\Services\PaymentService;
use App\Transformers\PaymentTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    protected $paymentService;
    protected $paymentRepository;

    public function __construct(PaymentService $paymentService, PaymentRepository $paymentRepository)
    {
        $this->paymentService = $paymentService;
        $this->paymentRepository = $paymentRepository;
    }

    public function index(): JsonResponse
    {
        $payments = $this->paymentRepository->getPaginated();
        return fractal($payments, new PaymentTransformer())->parseIncludes(request('include', ''))->respond();
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->createPayment($request->validated());
        return fractal($payment, new PaymentTransformer())->parseIncludes(['order', 'paymentMethod'])->respond(Response::HTTP_CREATED);
    }

    public function show(Payment $payment): JsonResponse
    {
        // El binding de ruta respeta el global scope de empresa (App\Src\Scopes\CompanyScope).
        $payment->load(['order', 'paymentMethod']);
        return fractal($payment, new PaymentTransformer())->parseIncludes(request('include', 'order,paymentMethod'))->respond();
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->paymentService->updatePayment($payment, $request->validated());
        return fractal($payment, new PaymentTransformer())->parseIncludes(['order', 'paymentMethod'])->respond();
    }

    public function destroy(Payment $payment): Response
    {
        $this->paymentService->deletePayment($payment);
        return response()->noContent();
    }
}