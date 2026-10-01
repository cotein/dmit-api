<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Order;
use App\Src\Repositories\OrderRepository;
use App\Src\Services\OrderService;
use App\Transformers\OrderTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    protected $orderService;
    protected $orderRepository;

    public function __construct(OrderService $orderService, OrderRepository $orderRepository)
    {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
    }

    /**
     * Muestra una lista paginada de pedidos.
     * Permite incluir relaciones con ?include=customer,items
     */
    public function index(): JsonResponse
    {
        $orders = $this->orderRepository->getPaginated();

        return fractal()
            ->collection($orders)
            ->transformWith(new OrderTransformer())
            ->parseIncludes(request('include', '')) // Lee el parámetro 'include' de la URL
            ->respond();
    }

    /**
     * Guarda un nuevo pedido.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $order = $this->orderService->createOrder($request->validated());

            // Devuelve el nuevo pedido, incluyendo sus relaciones por defecto para confirmación
            return fractal()
                ->item($order)
                ->transformWith(new OrderTransformer())
                ->parseIncludes(['customer', 'items'])
                ->respond(Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al crear el pedido.'], 500);
        }
    }

    /**
     * Muestra un pedido específico.
     */
    public function show(int $id): JsonResponse
    {
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return fractal()
            ->item($order)
            ->transformWith(new OrderTransformer())
            ->parseIncludes(request('include', 'customer,items,user')) // Incluye casi todo por defecto
            ->respond();
    }

    /**
     * Actualiza un pedido existente.
     */
    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        try {
            $updatedOrder = $this->orderService->updateOrder($order, $request->validated());

            return fractal()
                ->item($updatedOrder)
                ->transformWith(new OrderTransformer())
                ->parseIncludes(['customer', 'items'])
                ->respond();

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error al actualizar el pedido.'], 500);
        }
    }

    /**
     * Elimina un pedido.
     */
    public function destroy(Order $order): Response
    {
        $this->orderService->deleteOrder($order);

        return response()->noContent();
    }
}