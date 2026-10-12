<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Conceptos incluidos en el comprobante (4 filas, código de un dígito):
 * 1 Producto / Exportación definitiva de bienes, 2 Servicios, 3 Productos y
 * Servicios, 4 Otro. Es el valor que se informa como "concepto" en la factura
 * electrónica y el que decide si el comprobante lleva período facturado.
 *
 * CUIDADO CON LA HOMÓNIMA PARCIAL: existe `company_billing_concepts`, que está en
 * uso y es apuntada por `companies.billing_concept`. Ésa es la configuración de
 * facturación de cada empresa; ésta es el nomenclador de ARCA. Son cosas
 * distintas y ninguna de las dos se toca desde acá.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base conceptos
 */
class CreateAfipConceptosIncluidosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_conceptos_incluidos', function (Blueprint $table) {
            $table->increments('id');

            // Un solo dígito: 1 a 4 en el archivo de ARCA.
            $table->string('codigo', 1);

            // El más largo del archivo tiene 44 caracteres.
            $table->string('descripcion', 191);

            $table->timestamps();

            $table->unique('codigo', 'afip_conceptos_incluidos_codigo_unique');

            // Sin índice de descripción: 4 filas.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_conceptos_incluidos');
    }
}
