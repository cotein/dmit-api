<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Un COT: el registro 02 del archivo TXT, ya presentado a ARBA.
 *
 * El COT es una entidad propia y previa al traslado, NO una acción sobre una
 * factura. La factura entra sólo como documentación respaldatoria, referenciada
 * por sale_invoice_id; cuando no existe (carga manual) ese campo queda nulo y
 * mandan los datos de origen cargados a mano.
 *
 * Los largos de las columnas replican los del diseño de ARBA a propósito: el
 * generador del TXT no tiene que adivinar ni truncar nada, y un dato que no
 * entra en el campo se detecta al guardar y no al presentar el archivo.
 *
 * origen_cuit / destinatario_cuit están atados al sujeto generador:
 *   SUJETO_GENERADOR = 'E'  ->  origen_cuit         = CUIT de la empresa
 *   SUJETO_GENERADOR = 'D'  ->  destinatario_cuit   = CUIT de la empresa
 */
class CreateCotsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cots', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('cot_archivo_id')->unsigned()->nullable();
            $table->integer('company_id')->unsigned()->nullable();
            $table->integer('sale_invoice_id')->unsigned()->nullable();

            $table->string('sujeto_generador', 1)->nullable();
            $table->string('codigo_unico', 16)->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_salida_transporte')->nullable();
            $table->string('hora_salida_transporte', 4)->nullable();

            // Bloque destinatario.
            $table->boolean('destinatario_consumidor_final')->nullable()->default(0);
            $table->string('destinatario_tipo_documento', 3)->nullable();
            $table->string('destinatario_documento', 11)->nullable();
            $table->string('destinatario_cuit', 11)->nullable();
            $table->string('destinatario_razon_social', 50)->nullable();
            $table->boolean('destinatario_tenedor')->nullable()->default(0);

            // Domicilio de destino.
            $table->string('destino_domicilio_calle', 40)->nullable();
            $table->string('destino_domicilio_numero', 5)->nullable();
            $table->string('destino_domicilio_comple', 5)->nullable();
            $table->string('destino_domicilio_piso', 3)->nullable();
            $table->string('destino_domicilio_dto', 4)->nullable();
            $table->string('destino_domicilio_barrio', 30)->nullable();
            $table->string('destino_domicilio_codigo_postal', 8)->nullable();
            $table->string('destino_domicilio_localidad', 50)->nullable();
            $table->string('destino_domicilio_provincia', 1)->nullable();
            $table->string('propio_destino_domicilio_codigo', 20)->nullable();

            // Bloque origen.
            $table->boolean('entrega_domicilio_origen')->nullable()->default(1);
            $table->string('origen_cuit', 11)->nullable();
            $table->string('origen_razon_social', 50)->nullable();
            $table->boolean('emisor_tenedor')->nullable()->default(0);
            $table->string('origen_domicilio_calle', 40)->nullable();
            $table->string('origen_domicilio_numero', 5)->nullable();
            $table->string('origen_domicilio_comple', 5)->nullable();
            $table->string('origen_domicilio_piso', 3)->nullable();
            $table->string('origen_domicilio_dto', 4)->nullable();
            $table->string('origen_domicilio_barrio', 30)->nullable();
            $table->string('origen_domicilio_codigo_postal', 8)->nullable();
            $table->string('origen_domicilio_localidad', 50)->nullable();
            $table->string('origen_domicilio_provincia', 1)->nullable();

            // Transporte.
            $table->string('transportista_cuit', 11)->nullable();
            $table->string('tipo_recorrido', 1)->nullable();
            $table->string('recorrido_localidad', 50)->nullable();
            $table->string('recorrido_calle', 40)->nullable();
            $table->string('recorrido_ruta', 40)->nullable();
            $table->string('patente_vehiculo', 7)->nullable();
            $table->string('patente_acoplado', 7)->nullable();

            $table->boolean('producto_no_term_dev')->nullable()->default(0);
            $table->decimal('importe', 14, 2)->nullable();

            // Resultado de la presentación.
            $table->string('numero_cot', 20)->nullable();
            $table->boolean('procesado')->nullable();
            $table->text('errores')->nullable();

            $table->timestamps();

            $table->unique('codigo_unico');
            $table->index(['company_id', 'fecha_emision']);
            $table->index('cot_archivo_id');
            $table->index('sale_invoice_id');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('cots');
    }
}
