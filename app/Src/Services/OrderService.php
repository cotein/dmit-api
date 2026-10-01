<?php

namespace App\Src\Services;

use App\Models\Order;
use App\Src\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderService
{
    protected $orderRepository;

    public function __construct(OrderRepository $orderRepository)
    {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Crea un nuevo pedido con sus ítems.
     *
     * @throws Exception
     */
    public function createOrder(array $data): Order
    {
        $orderData = collect($data)->except('items')->toArray();
        $itemsData = collect($data)->get('items', []);

        DB::beginTransaction();
        try {
            // 1. Crear el pedido principal
            $order = $this->orderRepository->create($orderData);

            // 2. Crear los ítems asociados
            if ($itemsData) {
                $order->items()->createMany($itemsData);
            }
            
            // 3. (Opcional) Recalcular el total del pedido
            $this->recalculateOrderTotal($order);

            DB::commit();

            return $order;
        } catch (Exception $e) {
            DB::rollBack();
            // Podrías loguear el error aquí
            throw $e;
        }
    }

    /**
     * Actualiza un pedido y sus ítems.
     *
     * @throws Exception
     */
    public function updateOrder(Order $order, array $data): Order
    {
        $orderData = collect($data)->except('items')->toArray();
        $itemsData = collect($data)->get('items');
        
        DB::beginTransaction();
        try {
            // 1. Actualizar los datos del pedido principal
            $this->orderRepository->update($order, $orderData);

            // 2. Sincronizar ítems (eliminar los viejos y crear los nuevos)
            // Esta es una estrategia simple. Una más compleja podría actualizar/crear/eliminar individualmente.
            if (!is_null($itemsData)) {
                $order->items()->delete();
                $order->items()->createMany($itemsData);
            }
            
            // 3. Recalcular el total
            $this->recalculateOrderTotal($order);

            DB::commit();

            return $order->fresh(); // Retornar la instancia actualizada
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Elimina un pedido. La base de datos se encargará de los ítems en cascada.
     */
    public function deleteOrder(Order $order): bool
    {
        return $this->orderRepository->delete($order);
    }
    
    /**
     * Recalcula el total de un pedido basándose en la suma de sus ítems.
     */
    public function recalculateOrderTotal(Order $order): void
    {
        // Forzar la recarga de la relación por si se acaba de modificar
        $order->load('items'); 

        $total = $order->items->sum('total');

        // Aquí podrías sumar otros costos como 'aditional_pay_method' si aplica
        $order->total = $total;
        $order->save();
    }
}