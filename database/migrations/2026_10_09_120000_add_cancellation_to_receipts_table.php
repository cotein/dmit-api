<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anulación de recibos.
 *
 * Un recibo emitido no se borra: se anula, con fecha, motivo y usuario. La
 * anulación revierte la imputación sobre los comprobantes y devuelve el saldo,
 * por eso el recibo tiene que quedar auditable en lugar de desaparecer del
 * listado.
 *
 * Ojo: la tabla receipts NO tenía status_id. El filtro viejo del listado
 * consultaba esa columna inexistente (SQLSTATE 42S22 si alguien mandaba el
 * parámetro). El estado del recibo se resuelve con cancelled_at, que además
 * dice cuándo se anuló.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('saldo');
            $table->string('cancel_reason', 255)->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('cancelled_by')->nullable()->after('cancel_reason');

            $table->index('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropIndex(['cancelled_at']);
            $table->dropColumn(['cancelled_at', 'cancel_reason', 'cancelled_by']);
        });
    }
};
