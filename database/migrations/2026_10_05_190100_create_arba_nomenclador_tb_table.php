<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Nomenclador de Transporte de Bienes de ARBA.
 *
 * Es el catálogo que exige el registro 03 del archivo TXT: el
 * CODIGO_UNICO_PRODUCTO son SEIS posiciones y sale del nivel VARIEDAD, que es
 * el único con códigos de 6 dígitos.
 *
 * La jerarquía, tal como la publica ARBA (7.340 registros):
 *
 *   nivel 1  CAPITULO  2 dígitos   '02'
 *   nivel 2  RUBRO     4 dígitos   '0201'
 *   nivel 3  RUBRO     5 dígitos   '02013'   (subdivisión intermedia)
 *   nivel 3  VARIEDAD  6 dígitos   '020130'  <- lo que va al TXT
 *   nivel 4  VARIEDAD  6 dígitos   '020130'
 *
 * OJO: nivel no es la profundidad del árbol. Hay VARIEDADs en nivel 3 y en
 * nivel 4, así que para saber la profundidad hay que mirar el largo del código
 * (o el código del padre), no el campo nivel.
 *
 * El esquema es el del script que publica ARBA, incluida la FK auto-referencial
 * y el CHECK del tipo. Esa FK obliga a insertar los padres antes que los hijos:
 * el importador ordena por largo de código ascendente.
 */
class CreateArbaNomencladorTbTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arba_nomenclador_tb', function (Blueprint $table) {
            $table->string('codigo', 8)->primary();
            $table->string('codigo_padre', 8)->nullable();
            $table->smallInteger('nivel');
            $table->string('tipo', 12);
            $table->string('descripcion', 2000);
            $table->string('unidad_medida', 80)->nullable();

            $table->index('codigo_padre');
            $table->index('tipo');

            $table->foreign('codigo_padre')
                ->references('codigo')
                ->on('arba_nomenclador_tb');
        });

        // El script de ARBA trae el CHECK del tipo. MySQL lo hace cumplir desde
        // 8.0.16; en versiones anteriores lo ignora, así que el importador
        // valida igual del lado de PHP.
        DB::statement(
            "ALTER TABLE arba_nomenclador_tb ADD CONSTRAINT chk_arba_nomenclador_tipo "
            . "CHECK (tipo IN ('CAPITULO', 'RUBRO', 'VARIEDAD'))"
        );
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('arba_nomenclador_tb');
    }
}
