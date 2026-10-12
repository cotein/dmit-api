<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clase de remito que emite la empresa.
 *
 * La RG 1415, Anexo V distingue los remitos clase "R" de los clase "X":
 *
 *   - Clase "R": responsables inscriptos en el IVA. El punto 7 del mismo Anexo
 *     les exige datos de PREIMPRESIÓN (CAI, número de habilitación del
 *     establecimiento impresor, primero y último número del lote, fecha de
 *     vencimiento) que un sistema propio no puede generar solo: la salida es
 *     estar inscripto como autoimpresor.
 *
 *   - Clase "X": el punto 9.b.5 contempla expresamente los remitos impresos
 *     "mediante sistemas con tecnología láser o electrónica" por un
 *     autoimpresor no inscripto o exento. No arrastra datos de preimpresión.
 *
 * El valor queda a nivel EMPRESA y es opcional: si es nulo, el servicio lo
 * deriva de la condición frente al IVA (Responsable Inscripto -> "R",
 * cualquier otra -> "X"). Se deja configurable en vez de fijarlo por código
 * porque la clase correcta depende de la situación registral de cada empresa y
 * eso lo define el contador, no el sistema.
 *
 * OJO: cambiar este valor NO reescribe lo ya emitido. Cada remito guarda su
 * propia clase (columna `remitos.clase`) y su snapshot.
 */
class AddRemitoClaseToCompaniesTable extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->char('remito_clase', 1)->nullable()->after('pto_vta_remito_exterior');
        });
    }

    public function down()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('remito_clase');
        });
    }
}
