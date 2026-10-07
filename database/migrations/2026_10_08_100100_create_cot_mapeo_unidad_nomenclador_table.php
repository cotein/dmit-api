<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Puente entre la unidad de medida descriptiva del nomenclador y el código de
 * UN CARÁCTER que exige ARBA en el campo ARBA_CODIGO_UNIDAD_MEDIDA del registro
 * 03 del archivo TXT.
 *
 * Por qué existe: la columna unidad_medida del nomenclador es descriptiva
 * ("KILOGRAMO", "UNIDAD", "METRO CUBICO") y ARBA espera un solo carácter. La
 * tabla se cargó con los valores que pasó el dueño del sistema.
 *
 * El día que ARBA publique su tabla oficial de unidades, esta se reemplaza con
 * el mismo importador: la clave es la descripción del nomenclador.
 *
 * Sin esto, ARBA rechaza el archivo con el error 41 (código de unidad de medida
 * inválido).
 */
class CreateCotMapeoUnidadNomencladorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cot_mapeo_unidad_nomenclador', function (Blueprint $table) {
            $table->string('descripcion_nomenclador', 191)->primary();
            $table->string('codigo_unidad', 1);

            $table->foreign('codigo_unidad')
                ->references('codigo')
                ->on('cot_unidades_medida');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cot_mapeo_unidad_nomenclador');
    }
}
