<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Otros tributos del comprobante (18 códigos de 2 dígitos: 01 Impuestos
 * nacionales, 02 Impuestos provinciales, 03 Tributos municipales, 04 Impuestos
 * internos...). Es el catálogo que responde el servicio FEParamGetTiposTributos
 * de la factura electrónica y que va en el array OtrosTributos del comprobante.
 *
 * Por qué el nombre conserva el "otros": ARCA llama así al campo (OtrosTributos)
 * y la tabla NO es el listado de todos los tributos del sistema —IVA, Ganancias
 * e Ingresos Brutos no están acá—, es el nomenclador de los tributos que se
 * informan ADEMÁS del IVA. Con el nombre `afip_tributos` se invitaría a cargarle
 * cualquier cosa; el identificador conserva además el que estas filas ya tienen
 * en afip_parametros (colección `datos_base.otros_tributos`).
 *
 * La tercera columna del archivo NO tiene nombre en el encabezado, pero la fila
 * de título de ARCA sí lo dice ("Otros Tributos,,Observaciones"), así que la
 * columna se llama `observacion` y no queda anónima.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base tributos
 */
class CreateAfipOtrosTributosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_otros_tributos', function (Blueprint $table) {
            $table->increments('id');

            // 2 dígitos; el archivo arranca en 01.
            $table->string('codigo', 2);

            // El más largo del archivo tiene 37 caracteres.
            $table->string('descripcion', 191);

            // El archivo la deja vacía en los códigos 01..09 y 99, y la usa en los
            // regímenes de retención y percepción ("Sólo Controladores Fiscales",
            // "Liquidaciones Pecuarias", "Solo FE"): es la condición con la que se
            // puede informar cada uno, así que se guarda.
            $table->string('observacion', 191)->nullable();

            $table->timestamps();

            $table->unique('codigo', 'afip_otros_tributos_codigo_unique');

            // Sin índice de descripción: 18 filas.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_otros_tributos');
    }
}
