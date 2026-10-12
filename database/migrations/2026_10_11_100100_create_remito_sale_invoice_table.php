<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vínculo entre remitos y facturas.
 *
 * Por qué una tabla pivote y no un campo de texto.
 *
 * `remitos` y `sale_invoices` ya tenían un campo `commercial_reference` de
 * texto libre. No alcanza por dos motivos:
 *
 *   1. La RG 1415, Anexo II, Apartado A, Título I, inciso e) habla del
 *      "Número del o los remitos emitidos y vinculados con la operación":
 *      son VARIOS remitos por factura. Un campo de texto obliga a concatenar
 *      números y después no se puede consultar "¿qué remitos van con esta
 *      factura?" sin parsear.
 *
 *   2. Un remito puede facturarse en VARIAS facturas (entregas parciales).
 *      Eso es una relación de varios a varios, y necesita guardar además
 *      CUÁNTO de cada remito cubre cada factura.
 *
 * `importe` es cuánto de este remito se facturó en esa factura. La suma de los
 * importes de un remito nunca debería superar su total; el control va en el
 * servicio, no en la base, porque un remito puede facturarse con precios
 * distintos a los del remito.
 *
 * `commercial_reference` se deja como está: sigue sirviendo para referencias
 * que no son remitos del sistema (un pedido, una orden de compra del cliente).
 */
class CreateRemitoSaleInvoiceTable extends Migration
{
    public function up()
    {
        Schema::create('remito_sale_invoice', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('remito_id');
            $table->unsignedInteger('sale_invoice_id');
            $table->decimal('importe', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['remito_id', 'sale_invoice_id'], 'remito_factura_unico');
            $table->index('remito_id');
            $table->index('sale_invoice_id');
        });
    }

    public function down()
    {
        Schema::drop('remito_sale_invoice');
    }
}
