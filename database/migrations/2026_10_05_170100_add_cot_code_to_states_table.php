<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Código de provincia de ARBA para el COT (Código de Operación de Traslado).
 *
 * El diseño del archivo TXT de ARBA pide ORIGEN_DOMICILIO_PROVINCIA y
 * DESTINO_DOMICILIO_PROVINCIA como UN carácter, y el catálogo de errores lo
 * confirma: el error 90 rechaza el remito si ninguno de los dos es 'B'
 * ("Buenos Aires"). ARBA no publica la tabla de provincias en ninguno de los
 * instructivos, pero la letra coincide con el sufijo del código ISO 3166-2 que
 * ya está en states.codigo31662 (Buenos Aires = AR-B). Por eso el valor se
 * deriva de ahí y queda en columna propia: si ARBA llegara a usar otra tabla,
 * se corrige este único campo sin tocar el generador del TXT.
 */
class AddCotCodeToStatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->string('cot_code', 1)->nullable()->after('codigo31662');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('cot_code');
        });
    }
}
