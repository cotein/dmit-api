<?php

namespace App\Src\Repositories;

use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository
{
    protected $model;

    public function __construct(Order $model)
    {
        $this->model = $model;
    }

    /**
     * Obtiene una lista paginada de pedidos.
     */
    public function getPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model->with(['customer', 'status']) // Eager load para eficiencia
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Encuentra un pedido por su ID con relaciones detalladas.
     */
    public function findById(int $id): ?Order
    {
        return $this->model->with([
            'customer',
            'user',
            'status',
            'items',
            'items.product',
        ])->find($id);
    }

    /**
     * Crea un nuevo registro de pedido.
     */
    public function create(array $data): Order
    {
        return $this->model->create($data);
    }

    /**
     * Actualiza un pedido existente.
     */
    public function update(Order $order, array $data): bool
    {
        return $order->update($data);
    }

    /**
     * Elimina un pedido.
     */
    public function delete(Order $order): bool
    {
        return $order->delete();
    }
}