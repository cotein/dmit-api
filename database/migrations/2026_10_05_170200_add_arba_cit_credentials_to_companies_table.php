<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Credenciales del web service de Transporte de Bienes de ARBA (la CIT).
 *
 * Son POR EMPRESA, no globales: el COT se emite con el CUIT del emisor y cada
 * CUIT obtiene su propia Clave de Identificación de Transporte. Las credenciales
 * que hoy viven en el .env (ARBA_CUIT / ARBA_CLAVE) son del Domicilio Electrónico,
 * que es OTRO servicio de ARBA (consulta de alícuotas de IIBB) y no sirve para
 * emitir remitos: por eso van en columnas propias y no se reutilizan.
 *
 * arba_cit_password se guarda cifrada (cast 'encrypted' en el modelo Company).
 */
class AddArbaCitCredentialsToCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('arba_cit_user', 191)->nullable()->after('iibb_conv');
            $table->text('arba_cit_password')->nullable()->after('arba_cit_user');
            $table->string('arba_cit_environment', 20)->nullable()->default('testing')->after('arba_cit_password');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['arba_cit_user', 'arba_cit_password', 'arba_cit_environment']);
        });
    }
}
