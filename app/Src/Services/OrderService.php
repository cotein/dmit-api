<?php

namespace App\Src\Services;

use App\Models\Order;
use App\Src\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
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
        // La empresa la fija BelongsToCompanyTrait desde el usuario autenticado:
        // nunca la que manda el cliente.
        $orderData = collect($data)->except(['items', 'company_id'])->toArray();
        $orderData['user_id'] = $orderData['user_id'] ?? auth()->id();
        $itemsData = $this->normalizeItems(collect($data)->get('items', []));

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
        $orderData = collect($data)->except(['items', 'company_id'])->toArray();
        $itemsData = collect($data)->get('items');
        $itemsData = is_null($itemsData) ? null : $this->normalizeItems($itemsData);
        
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

            return $order->fresh(['customer', 'items', 'user', 'status']); // Retornar la instancia actualizada
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
     * Recalcula en el servidor el neto, el descuento, el IVA y el total de cada ítem.
     * El total nunca debe venir decidido por el cliente.
     *
     * Con orders.strict_totals = false (default) las diferencias se registran como
     * warning; con true, el pedido se rechaza con 422.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return collect($items)->map(function (array $item): array {
            $quantity = (float) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $ivaPercentage = (float) $item['iva_percentage'];
            $discountPercentage = (float) ($item['discount_percentage'] ?? 0);

            $neto = round($quantity * $unitPrice, 2);
            $discountImport = round($neto * $discountPercentage / 100, 2);
            $base = round($neto - $discountImport, 2);
            $ivaImport = round($base * $ivaPercentage / 100, 2);
            $total = round($base + $ivaImport, 2);

            if (isset($item['total']) && abs((float) $item['total'] - $total) > 0.01) {
                $context = [
                    'product_id' => $item['product_id'] ?? null,
                    'recibido' => (float) $item['total'],
                    'esperado' => $total,
                ];

                if (config('orders.strict_totals')) {
                    throw ValidationException::withMessages([
                        'items' => 'El total del producto '.($item['product_id'] ?? '?')
                            ." no coincide: recibido {$context['recibido']}, esperado {$context['esperado']}.",
                    ]);
                }

                Log::warning('Orden con total de ítem inconsistente', $context);
            }

            return array_merge($item, [
                'neto_import' => $base,
                'discount_import' => $discountImport,
                'iva_import' => $ivaImport,
                'total' => $total,
            ]);
        })->all();
    }

    /**
     * Recalcula el total de un pedido basándose en la suma de sus ítems.
     */
    public function recalculateOrderTotal(Order $order): void
    {
        // Forzar la recarga de la relación por si se acaba de modificar
        $order->load('items');

        // Aquí podrías sumar otros costos como 'aditional_pay_method' si aplica
        $order->total = round((float) $order->items->sum('total'), 2);
        $order->save();
    }
}