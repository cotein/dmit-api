<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Cruce operación × condición frente al IVA (10 filas, código de UN dígito).
 *
 * Este catálogo es el que explica cuándo corresponde cada comprobante: dice, para
 * cada combinación de operación y condición del emisor, cómo se trata el IVA ("0
 * No Corresponde - NO APLICA PARA FACTURA ELECTRONICA", "1 No Gravado", "2
 * Exento", "3 0%", "4 10.5%", "5 21%", "6 27%", "7 Gravado", "8 5%", "9 2.5%").
 * Por eso NO es lo mismo que `afip_inscriptions`, que lista las condiciones frente
 * al IVA solas: acá el dato es el cruce.
 *
 * La tercera columna no tiene nombre en el encabezado del archivo; se llama
 * `observacion` porque es lo que es, y sólo el código 0 la trae.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base operacion_condicion_iva
 */
class CreateAfipOperacionCondicionIvaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_operacion_condicion_iva', function (Blueprint $table) {
            $table->increments('id');

            // Un solo dígito: 0 a 9.
            $table->string('codigo', 1);

            // El más largo del archivo tiene 14 caracteres.
            $table->string('descripcion', 191);

            // Sólo la fila 0 la trae; el resto viene vacía.
            $table->string('observacion', 191)->nullable();

            $table->timestamps();

            $table->unique('codigo', 'afip_operacion_condicion_iva_codigo_unique');

            // Sin índice de descripción: 10 filas.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_operacion_condicion_iva');
    }
}
