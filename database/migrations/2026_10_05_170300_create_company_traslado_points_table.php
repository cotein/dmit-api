<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Puntos de traslado habilitados en ARBA por establecimiento.
 *
 * El nombre del archivo TXT exige el par planta + puerta
 * (TB_[CUIT]_[Planta][Puerta]_[AAAAMMDD]_[Secuencia].txt) y esos dos números
 * los habilita ARBA por domicilio. Una empresa puede tener más de un depósito
 * habilitado, así que no alcanza con dos columnas en companies: va una fila
 * por establecimiento.
 *
 * El domicilio se guarda acá con el largo que pide el registro 02 del TXT, que
 * no coincide con el de la tabla addresses (que no tiene piso, depto, barrio,
 * ni el campo "comple" con los valores S/N, 1/2, 1/4, BIS).
 */
class CreateCompanyTrasladoPointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('company_traslado_points', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100)->nullable();
            $table->integer('company_id')->unsigned()->nullable();
            $table->string('planta', 3)->nullable();
            $table->string('puerta', 3)->nullable();

            $table->string('street', 40)->nullable();
            $table->string('number', 5)->nullable();
            $table->string('comple', 5)->nullable();
            $table->string('floor', 3)->nullable();
            $table->string('apartment', 4)->nullable();
            $table->string('neighbourhood', 30)->nullable();
            $table->string('codigo_postal', 8)->nullable();
            $table->string('city', 50)->nullable();
            $table->integer('state_id')->unsigned()->nullable();

            $table->boolean('active')->nullable()->default(1);
            $table->timestamps();

            $table->unique(['company_id', 'planta', 'puerta'], 'traslado_point_company_planta_puerta_unique');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('company_traslado_points');
    }
}
