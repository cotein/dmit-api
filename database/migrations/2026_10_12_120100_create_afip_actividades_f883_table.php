<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Nomenclador de actividades económicas del formulario F883 (958 códigos de 6
 * dígitos), el que ARCA publica como ACTIVIDADES_ECONOMICAS_F883.
 *
 * Por qué el nombre lleva F883 y no es sólo `afip_actividades`: ARCA tiene más
 * de un nomenclador de actividades y este mismo proyecto ya va a convivir con
 * ellos. El módulo del padrón (app/Src/Afip/Padron100/ColeccionesPadron100.php)
 * maneja SUPA.A_ACTIVIDAD_RUBRO y SUPA.E_ACTIVIDAD_OCUPACIONAL, que son listas
 * DISTINTAS de la del F883. Llamar `afip_actividades` a ésta obligaría a
 * renombrarla (o peor, a mezclar dos listas) el día que el padrón tenga su tabla.
 * El nombre mantiene además el identificador con el que estas filas ya están
 * guardadas hoy en afip_parametros (colección `datos_base.actividades_f883`), así
 * que el traslado de los datos es rastreable uno a uno.
 *
 * Por qué `descripcion_larga` va en TEXT: el archivo trae dos descripciones por
 * actividad —la corta y la larga con los productos incluidos—, y la larga llega
 * a 536 caracteres. No se indexa: no se busca por ella, se muestra.
 *
 * Lo carga:
 *   php artisan afip:import-datos-base actividades
 */
class CreateAfipActividadesF883Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_actividades_f883', function (Blueprint $table) {
            $table->increments('id');

            // Siempre 6 dígitos en el archivo (011111, 011112...).
            $table->string('codigo', 6);

            // El más largo del archivo tiene 183 caracteres; 191 es el largo que
            // el proyecto usa en las columnas indexadas con utf8mb4.
            $table->string('descripcion', 191);

            // DESC_ACTIVIDAD_F883 es la corta del formulario y
            // DESCL_ACTIVIDA_F883 (así, con la errata de ARCA) la larga.
            $table->text('descripcion_larga')->nullable();

            $table->timestamps();

            $table->unique('codigo', 'afip_actividades_f883_codigo_unique');

            // Es el catálogo más grande de los ocho (958 filas) y el que más se
            // consulta por texto: buscar "Cultivo de" no puede recorrer la tabla.
            $table->index('descripcion', 'afip_actividades_f883_descripcion_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('afip_actividades_f883');
    }
}
