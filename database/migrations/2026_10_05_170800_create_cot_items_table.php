<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Mercadería de un COT: el registro 03 del archivo TXT.
 *
 * Cada COT necesita AL MENOS un registro 03. Sin él ARBA rechaza el archivo con
 * el error 46 ("No hay registro 03=PRODUCTOS") o el 15 ("No puede procesar el
 * registro 03-PRODUCTOS. Faltan datos"): es obligatorio aunque la mercadería no
 * se discrimine comercialmente.
 *
 * cantidad y cantidad_ajustada van con 2 decimales porque el TXT las pide con
 * "valores decimales obligatorios" (si la cantidad es 200 se envía 20000).
 */
class CreateCotItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cot_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cot_id')->unsigned()->nullable();

            $table->string('codigo_unico_producto', 6)->nullable();
            $table->string('arba_codigo_unidad_medida', 1)->nullable();
            $table->decimal('cantidad', 15, 2)->nullable();

            $table->string('propio_codigo_producto', 25)->nullable();
            $table->string('propio_descripcion_producto', 40)->nullable();
            $table->string('propio_descripcion_unidad_medida', 20)->nullable();
            $table->decimal('cantidad_ajustada', 15, 2)->nullable();

            $table->timestamps();

            $table->index('cot_id');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cot_items');
    }
}
