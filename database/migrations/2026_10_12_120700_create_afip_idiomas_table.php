<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Idiomas de ARCA (3 filas, código de un dígito): 1 Español, 2 Inglés,
 * 3 Portugués. Es el idioma del comprobante de exportación.
 *
 * Tres filas no justifican ninguna optimización, pero sí una tabla propia: el
 * catálogo tiene que poder consultarse y validarse como cualquier otro, y mientras
 * vivía dentro del JSON de afip_parametros no había forma de preguntarle nada a la
 * base ("¿este código existe?") sin deserializar la colección entera.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base idiomas
 */
class CreateAfipIdiomasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_idiomas', function (Blueprint $table) {
            $table->increments('id');

            // Un solo dígito: 1 a 3.
            $table->string('codigo', 1);

            // "Portugués" tiene 8 caracteres; 191 es el largo del resto del módulo.
            $table->string('descripcion', 191);

            $table->timestamps();

            $table->unique('codigo', 'afip_idiomas_codigo_unique');

            // Sin índice de descripción: 3 filas.
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_idiomas');
    }
}
