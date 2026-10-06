<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla de unidades de medida de ARBA para el COT.
 *
 * El registro 03 pide ARBA_CODIGO_UNIDAD_MEDIDA, de UN carácter, "válido según
 * tabla de unidad de medida". Igual que el nomenclador, la tabla no está en los
 * instructivos: se carga con el importador.
 */
class CreateCotUnidadesMedidaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cot_unidades_medida', function (Blueprint $table) {
            $table->increments('id');
            $table->string('codigo', 1);
            $table->string('descripcion', 191)->nullable();
            $table->boolean('active')->nullable()->default(1);
            $table->timestamps();

            $table->unique('codigo');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cot_unidades_medida');
    }
}
