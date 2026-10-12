<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Condición de venta del remito.
 *
 * La tabla `remitos` original sólo tenía `payment_type_id` (modo de pago). La
 * condición de venta es otro dato: contado, cuenta corriente a 30 días, etc.
 * La usa el mismo catálogo que la factura (`sale_conditions`), así que se
 * referencia igual, con `sales_condition_id`.
 *
 * Va en el remito y no sólo en la factura porque el remito se emite ANTES de
 * facturar: es el papel que viaja con la mercadería y el que firma quien
 * recibe.
 */
class AddSalesConditionToRemitosTable extends Migration
{
    public function up()
    {
        Schema::table('remitos', function (Blueprint $table) {
            $table->unsignedInteger('sales_condition_id')->nullable()->after('payment_type_id');
        });
    }

    public function down()
    {
        Schema::table('remitos', function (Blueprint $table) {
            $table->dropColumn('sales_condition_id');
        });
    }
}
