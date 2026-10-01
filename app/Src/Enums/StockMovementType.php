<?php

namespace App\Src\Enums;

enum StockMovementType: string
{
    case SALE = 'sale';                     // Venta de un pedido
    case SALE_RETURN = 'sale_return';       // Devolución de un cliente
    case PURCHASE = 'purchase';             // Compra a un proveedor
    case MANUAL_ADJUSTMENT = 'manual_adjustment'; // Ajuste manual de un admin
    case INITIAL_STOCK = 'initial_stock';   // Carga inicial de stock
}