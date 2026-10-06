<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * orders.voucher_id tenía default(101) apuntando a un voucher que no existe: el
 * INSERT fallaba con FK 1452 (no se podía crear ningún pedido).
 *
 * La migración original ya se corrigió para instalaciones nuevas; esto arregla las
 * bases donde la tabla orders ya existe (dev). No es destructivo: no toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['orders', 'voucher_id']
        );

        if (! $column) {
            return;
        }

        if ($column->IS_NULLABLE === 'YES' && $column->COLUMN_DEFAULT === null) {
            return; // ya está corregida
        }

        // Se conserva el tipo actual de la columna: sólo se quita el default y se
        // permite NULL (la FK sigue válida porque el tipo no cambia).
        DB::statement("ALTER TABLE `orders` MODIFY `voucher_id` {$column->COLUMN_TYPE} NULL DEFAULT NULL");
    }

    /**
     * Sin reversa a propósito: restaurar el default(101) volvería a romper la
     * creación de pedidos (y fallaría si ya hay filas con voucher_id NULL).
     */
    public function down(): void
    {
    }
};
