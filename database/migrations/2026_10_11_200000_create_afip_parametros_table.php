<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Parámetros crudos del WS ws_sr_padron_a100 ("Consulta a parámetros de padrón").
 *
 * Por qué existe: el servicio devuelve catálogos enteros de ARCA (tipos de documento,
 * tipos de clave, regímenes, formas jurídicas, organismos, actividades...) y de esos
 * catálogos hoy el sistema sólo usa unos pocos en tablas propias. Guardando la respuesta
 * tal como llega se puede rehacer el mapeo (o agregar una tabla nueva, como pidió el
 * dueño) SIN volver a llamar a ARCA ni depender del certificado.
 *
 * La clave natural es (collection_name, parametro_id) porque el propio manual dice que
 * el atributo "id" identifica unívocamente a cada registro dentro de su colección: con
 * esa restricción única la sincronización es idempotente a nivel base.
 *
 * atributos va como JSON porque cada colección trae un juego de atributos distinto
 * (COD_PROVINCIA + CODIGO_SIM_PROVINCIA, COD_TIPO_ARCHIVO, COD_ACTIVIDAD:COD_RUBRO...)
 * que no tiene sentido modelar columna por columna.
 */
class CreateAfipParametrosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('afip_parametros', function (Blueprint $table) {
            $table->increments('id');

            // 191 es el largo que usa el proyecto en columnas indexadas con utf8mb4.
            $table->string('collection_name', 191);
            $table->string('parametro_id', 191);

            // Puede faltar: el manual escribe este campo de dos formas ("decription" en
            // el ejemplo y "description" en el esquema) y si ARCA no manda ninguna de las
            // dos, se guarda el registro igual y se avisa.
            $table->string('descripcion', 191)->nullable();

            $table->json('atributos')->nullable();

            // Cuándo se trajo de ARCA. No se actualiza en cada corrida: sólo cuando el
            // contenido cambió o el registro es nuevo (ver AlmacenPadron100).
            $table->timestamp('sincronizado_at')->nullable();

            $table->timestamps();

            $table->unique(['collection_name', 'parametro_id'], 'afip_parametros_coleccion_parametro_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('afip_parametros');
    }
}
