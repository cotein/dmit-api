<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Países y zonas francas de la TABLA 20 PAISES de ARCA (la que pide el campo
 * "pais" de la exportación y del comprobante de exportación).
 *
 * Por qué tiene una tabla propia y no va a afip_parametros: son 309 códigos con
 * su nombre y su país de pertenencia, y el archivo trae DOS datos distintos por
 * fila. Las zonas francas lo prueban: el código 107 es "AAE Tierra del Fuego -
 * ARGENTINA" y su PAIS es "ARGENTINA", mientras que el 117 es "ZF Iquique" con
 * PAIS "CHILE". Con una sola columna de descripción esa diferencia se perdía.
 *
 * La clave natural es `codigo` (3 dígitos, sin repetir en el archivo) y lleva
 * UNIQUE para que el importador sea idempotente a nivel base: aunque el
 * importador compare antes de escribir, la restricción es la red que impide que
 * dos corridas simultáneas dejen el catálogo duplicado.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base paises
 */
class CreateAfipPaisesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_paises', function (Blueprint $table) {
            $table->increments('id');

            // Hasta 3 caracteres (001..999). VARCHAR y no CHAR: CHAR rellena con
            // espacios y el importador compara los valores guardados, así que el
            // relleno haría que toda fila pareciera distinta en cada corrida.
            $table->string('codigo', 3);

            // 66 caracteres es el más largo del archivo (con margen hasta 191,
            // que es el largo que el proyecto usa en las columnas indexadas).
            $table->string('descripcion', 191);

            // El país del que depende la zona franca; en los países comunes
            // repite la descripción, pero no siempre (ver el docblock).
            $table->string('pais', 191)->nullable();

            $table->timestamps();

            $table->unique('codigo', 'afip_paises_codigo_unique');

            // El catálogo se va a consultar por texto (buscar un país escribiendo
            // su nombre), y con cientos de filas el índice es lo que evita el
            // recorrido completo en cada búsqueda.
            $table->index('descripcion', 'afip_paises_descripcion_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_paises');
    }
}
