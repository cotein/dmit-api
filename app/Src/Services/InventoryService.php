<?php

namespace App\Src\Services;

use App\Src\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Maneja un movimiento de stock de manera transaccional.
     *
     * @param Product $product El producto a modificar.
     * @param int $quantity La cantidad a mover (positiva para agregar, negativa para quitar).
     * @param StockMovementType $type El tipo de movimiento.
     * @param Model $source El modelo que origina el movimiento (Order, User, etc.).
     * @param string|null $description Una descripción opcional.
     */
    public function recordMovement(
        Product $product,
        int $quantity,
        StockMovementType $type,
        Model $source,
        ?string $description = null
    ): void {
        DB::transaction(function () use ($product, $quantity, $type, $source, $description) {
            // 1. Actualizar el stock del producto de forma atómica
            // Usar increment/decrement previene problemas de concurrencia (race conditions)
            if ($quantity > 0) {
                $product->increment('stock', $quantity);
            } else {
                $product->decrement('stock', abs($quantity));
            }

            // Refrescar el modelo para obtener el valor de stock actualizado
            $product->refresh();

            // 2. Crear el registro en el historial de movimientos
            StockMovement::create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'stock_after_change' => $product->stock,
                'type' => $type->value,
                'sourceable_id' => $source->id,
                'sourceable_type' => get_class($source),
                'description' => $description,
            ]);
        });
    }
}