<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Unidades de medida de ARCA (54 códigos de 2 dígitos: 00 SIN DESCRIPCION,
 * 01 KILOGRAMO, 02 METROS...). Es el catálogo que pide el campo de unidad de
 * medida de los ítems del comprobante.
 *
 * CUIDADO CON LA HOMÓNIMA: existe `cot_unidades_medida`, que es la unidad de
 * medida de ARBA para el COT y su código es de UN carácter (ARBA_CODIGO_UNIDAD_MEDIDA
 * del registro 03). No tienen nada que ver entre sí: son dos organismos, dos
 * registros y dos juegos de códigos distintos, y los importadores tampoco se
 * cruzan. El prefijo `afip_` es lo que mantiene separadas a las dos.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base unidades
 */
class CreateAfipUnidadesMedidaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_unidades_medida', function (Blueprint $table) {
            $table->increments('id');

            // 2 dígitos, incluido el 00 (que es un código válido, no un hueco).
            $table->string('codigo', 2);

            // El más largo del archivo tiene 28 caracteres.
            $table->string('descripcion', 191)->nullable();

            $table->timestamps();

            $table->unique('codigo', 'afip_unidades_medida_codigo_unique');

            // Sin índice de descripción: son 54 filas, un recorrido completo
            // cuesta menos que mantener el índice.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_unidades_medida');
    }
}
