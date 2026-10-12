<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de emisión del remito.
 *
 * La tabla `remitos` existe desde 2023 pero nunca se usó: no tiene modelo,
 * ni controller, ni rutas, ni pantalla. Tenía lo mínimo para cargar un
 * remito a mano (number, delivery_date, customer_id) y nada de lo que exige
 * la RG 1415, Anexo V - "DATOS QUE DEBEN CONTENER LOS REMITOS, LAS GUIAS, O
 * DOCUMENTOS EQUIVALENTES".
 *
 * Lo que se agrega acá y por qué:
 *
 * - pto_vta: la numeración del Anexo V es de DOCE dígitos = CUATRO que
 *   identifican el lugar de emisión + OCHO del número. El punto de venta sale
 *   de companies.pto_vta_remito, que ya existía sin usarse.
 *
 * - clase: "R" o "X" según el Anexo V, inciso I, punto 8. Responsable
 *   Inscripto va clase "R" (y arrastra datos de preimpresión); el resto va
 *   clase "X". Se guarda por remito y no sólo por empresa para que un cambio
 *   de condición frente al IVA no reescriba lo ya emitido.
 *
 * - estado / emitido_at: un remito en borrador no es un documento. Recién al
 *   emitirlo se congela el snapshot y se consume el número.
 *
 * - snapshot: el documento EMITIDO, congelado en JSON. Es lo que permite
 *   reimprimir en cualquier momento y que salga idéntico: sin esto, un cambio
 *   de domicilio de la empresa o de nombre de un producto alteraría el papel
 *   que ya se entregó al cliente.
 *
 * - direccion_entrega: puede no coincidir con el domicilio del cliente.
 *
 * - transportista_*: el Anexo V, inciso IV lo exige "sólo cuando el traslado se
 *   efectúe por terceros", por eso los tres campos son nulos.
 *
 * - anulado_at / anulado_motivo: el Anexo V, inciso V, punto 11.b dice que el
 *   documento que queda en existencia sin usar se inutiliza con la leyenda
 *   "ANULADO" y se conserva archivado. Un remito anulado no se borra.
 */
class AddEmisionFieldsToRemitosTable extends Migration
{
    public function up()
    {
        Schema::table('remitos', function (Blueprint $table) {
            $table->unsignedSmallInteger('pto_vta')->nullable()->after('number');
            $table->char('clase', 1)->default('R')->after('pto_vta');
            $table->string('estado', 20)->default('borrador')->after('clase');
            $table->timestamp('emitido_at')->nullable()->after('estado');
            $table->timestamp('anulado_at')->nullable()->after('emitido_at');
            $table->string('anulado_motivo', 191)->nullable()->after('anulado_at');
            $table->text('direccion_entrega')->nullable()->after('delivery_date');
            $table->string('transportista_nombre', 191)->nullable()->after('direccion_entrega');
            $table->string('transportista_cuit', 20)->nullable()->after('transportista_nombre');
            $table->string('transportista_domicilio', 191)->nullable()->after('transportista_cuit');
            $table->text('observaciones')->nullable()->after('transportista_domicilio');
            $table->longText('snapshot')->nullable()->after('observaciones');

            // Un punto de venta no puede repetir número dentro de la misma empresa.
            $table->unique(['company_id', 'pto_vta', 'number'], 'remitos_numero_unico');
        });
    }

    public function down()
    {
        Schema::table('remitos', function (Blueprint $table) {
            $table->dropUnique('remitos_numero_unico');
            $table->dropColumn([
                'pto_vta',
                'clase',
                'estado',
                'emitido_at',
                'anulado_at',
                'anulado_motivo',
                'direccion_entrega',
                'transportista_nombre',
                'transportista_cuit',
                'transportista_domicilio',
                'observaciones',
                'snapshot',
            ]);
        });
    }
}
