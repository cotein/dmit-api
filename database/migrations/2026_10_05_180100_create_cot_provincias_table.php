<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Tabla de provincias del COT de ARBA.
 *
 * El campo ORIGEN_DOMICILIO_PROVINCIA / DESTINO_DOMICILIO_PROVINCIA del registro
 * 02 es de UN carácter alfabético, así que el código es la clave natural de esta
 * tabla: no hay ningún id numérico que aporte algo.
 *
 * El contenido sale de App\Src\Arba\Cot\CotProvincias, que es la única fuente de
 * verdad, y lo carga:
 *   php artisan cot:import-catalogs provincias
 */
class CreateCotProvinciasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cot_provincias', function (Blueprint $table) {
            $table->char('codigo', 1)->primary();
            $table->string('descripcion', 50);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cot_provincias');
    }
}
