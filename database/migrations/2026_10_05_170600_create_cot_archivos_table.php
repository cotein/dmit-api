<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Bitácora de cada archivo TXT presentado a ARBA.
 *
 * Existe por dos motivos concretos de la operatoria:
 *
 * 1. Idempotencia. ARBA identifica el envío por el NOMBRE del archivo y
 *    responde el error 11 ("El archivo recibido ya fue procesado con
 *    anterioridad"). Guardar el nombre y el contenido permite reintentar sin
 *    duplicar y distinguir "ya estaba emitido" de "falló de verdad".
 * 2. Reproceso. Se guarda el TXT enviado y el XML de respuesta crudos para
 *    poder reenviar un archivo rechazado sin reconstruirlo.
 *
 * estado: pendiente | enviado | procesado | rechazado | error
 */
class CreateCotArchivosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cot_archivos', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('user_id')->unsigned()->nullable();
            $table->integer('company_traslado_point_id')->unsigned()->nullable();

            $table->string('nombre_archivo', 191);
            $table->string('cuit_empresa', 11)->nullable();
            $table->string('planta', 3)->nullable();
            $table->string('puerta', 3)->nullable();
            $table->date('fecha_archivo')->nullable();
            $table->string('secuencia', 6)->nullable();
            $table->unsignedInteger('cantidad_remitos')->nullable();

            $table->longText('contenido')->nullable();
            $table->string('hash', 64)->nullable();

            $table->string('estado', 20)->nullable()->default('pendiente');
            $table->integer('http_status')->nullable();

            // Devuelve ARBA en el TBCOMPROBANTE (N9). El número de COT por
            // remito no viene en esta respuesta: ver cots.numero_cot.
            $table->string('numero_comprobante', 20)->nullable();
            $table->string('codigo_integridad', 50)->nullable();

            $table->longText('respuesta')->nullable();
            $table->string('error_tipo', 20)->nullable();
            $table->string('error_codigo', 10)->nullable();
            $table->text('error_mensaje')->nullable();

            $table->dateTime('enviado_at')->nullable();
            $table->timestamps();

            $table->unique('nombre_archivo');
            $table->index(['company_id', 'estado']);
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cot_archivos');
    }
}
