<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Incoterms (14 códigos de 3 letras: EXW, FCA, FAS, FOB... los términos de
 * comercio internacional que pide el comprobante de exportación).
 *
 * El encabezado de este archivo está MAL FORMADO en el original de ARCA: declara
 * una sola columna ("Código,") y las filas traen dos. La descripción igual se
 * importa, porque su posición es fija y conocida (por eso la configuración la
 * declara como posición sin nombre en vez de resignar el dato); si ARCA publicara
 * una versión del archivo con otra forma, la validación de columnas del lector lo
 * corta antes de escribir.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base incoterms
 */
class CreateAfipIncotermsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_incoterms', function (Blueprint $table) {
            $table->increments('id');

            // Siempre 3 letras mayúsculas (EXW..DAP).
            $table->string('codigo', 3);

            // El más largo del archivo tiene 30 caracteres.
            $table->string('descripcion', 191);

            $table->timestamps();

            $table->unique('codigo', 'afip_incoterms_codigo_unique');

            // Sin índice de descripción: 14 filas.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_incoterms');
    }
}
