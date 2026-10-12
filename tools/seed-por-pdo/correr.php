<?php

/**
 * Runner de los seeders y de los importadores de catálogos, por PDO.
 *
 * POR QUÉ EXISTE: `php artisan db:seed` no puede correr en esta máquina (ver el
 * docblock de EntornoLaravel.php). Este script hace el trabajo equivalente —en el
 * mismo orden que DatabaseSeeder manda— ejecutando los archivos REALES del
 * proyecto: no hay una sola fila copiada a mano acá.
 *
 * ORDEN (y por qué es ése):
 *   1) Los seeders. Truncan y recargan, así que van primero: lo que había cargado
 *      antes se pierde, incluido vouchers.inscription_id, que es lo que hay que
 *      recuperar.
 *   2) cot:import-catalogs provincias. Va después del seeder de estados porque
 *      deriva states.cot_code de states.codigo31662, que es una columna que carga
 *      el seeder. Si corriera antes, no habría de dónde derivarlo.
 *   3) afip:import-datos-base --todos. Va al final porque el seeder de comprobantes
 *      trae 102 códigos y el archivo de ARCA 109: los 7 que faltan (109, 991-995 y
 *      997) sólo se pueden agregar después de que el truncado borró la tabla.
 *
 * Uso:  php tools/seed-por-pdo/correr.php
 */

$raiz = dirname(__DIR__, 2);

require __DIR__ . '/Conexion.php';
require __DIR__ . '/FabricaStub.php';
require __DIR__ . '/ReceptorTabla.php';
require __DIR__ . '/EntornoLaravel.php';
require __DIR__ . '/EjecutorSeeders.php';
require __DIR__ . '/AplicadorOperaciones.php';
require __DIR__ . '/Estado.php';
require __DIR__ . '/AplicadorCotProvincias.php';
require __DIR__ . '/ConstructorConsulta.php';
require __DIR__ . '/ConstructorEsquema.php';

// Módulos del proyecto que se ejecutan de verdad. Se cargan a mano y no por el
// autoload de vendor a propósito: cargar vendor traería las clases de Laravel de
// verdad y los stubs dejarían de usarse.
require $raiz . '/app/Src/Afip/DatosBase/EscritorDatosBase.php';
require $raiz . '/app/Src/Afip/DatosBase/ConfiguracionDatosBase.php';
require $raiz . '/app/Src/Afip/DatosBase/LectorDatosBase.php';
require $raiz . '/app/Src/Afip/DatosBase/ImportadorDatosBase.php';
require $raiz . '/app/Src/Afip/DatosBase/EscritorDatosBaseLaravel.php';
require $raiz . '/app/Src/Arba/Cot/CotProvincias.php';
require $raiz . '/app/Console/Commands/ImportAfipDatosBase.php';

use Tools\SeedPorPdo\AplicadorCotProvincias;
use Tools\SeedPorPdo\AplicadorOperaciones;
use Tools\SeedPorPdo\Conexion;
use Tools\SeedPorPdo\EjecutorSeeders;
use Tools\SeedPorPdo\Estado;

$pdo = Conexion::abrir($raiz);

echo "== Estado ANTES ==\n";
$antes = Estado::capturar($pdo);
foreach ($antes['conteos'] as $tabla => $n) {
    printf("  %-30s %s\n", $tabla, $n === null ? '(no existe)' : $n);
}

// ---------------------------------------------------------------------------
// 1) Seeders
// ---------------------------------------------------------------------------
echo "\n== 1) Seeders ==\n";

$ejecutor = new EjecutorSeeders($raiz);
$aplicador = new AplicadorOperaciones($pdo);

$databaseSeeder = $ejecutor->correrDatabaseSeeder();

$filas = $aplicador->aplicar($databaseSeeder['altas']);
echo "  DatabaseSeeder (altas propias con factorías): {$filas} fila(s)\n";

foreach ($databaseSeeder['seeders'] as $clase) {
    $resultado = $ejecutor->correr($clase);

    $efecto = EjecutorSeeders::efectoPorTabla($resultado['operaciones']);
    $resumen = [];

    foreach ($efecto as $tabla => $tipo) {
        $resumen[] = $tabla . ' (' . $tipo . ')';
    }

    $n = $aplicador->aplicar($resultado['operaciones']);

    printf(
        "  %-38s %4d fila(s)  %s\n",
        preg_replace('/^.*\\\\/', '', $clase),
        $n,
        implode(', ', $resumen)
    );
}

foreach ($aplicador->avisos() as $aviso) {
    echo "    aviso: {$aviso}\n";
}

// ---------------------------------------------------------------------------
// 2) Provincias del COT
// ---------------------------------------------------------------------------
echo "\n== 2) cot:import-catalogs provincias ==\n";

$aplicadorCot = new AplicadorCotProvincias($pdo);
$aplicadorCot->aplicar(false);

echo "  cot_provincias: {$aplicadorCot->provinciasEscritas()} fila(s) (updateOrInsert)\n";
echo "  states enlazados con cot_code: {$aplicadorCot->estadosEnlazados()}\n";
echo "  states sin código COT: {$aplicadorCot->estadosSinCodigo()}\n";

foreach ($aplicadorCot->avisos() as $aviso) {
    echo "    aviso: {$aviso}\n";
}

// ---------------------------------------------------------------------------
// 3) Catálogos de datos base de ARCA
// ---------------------------------------------------------------------------
echo "\n== 3) afip:import-datos-base --todos ==\n";

/**
 * Ejecuta la CLASE REAL del comando: lo que corre es ImportAfipDatosBase::handle(),
 * con la fachada DB en modo PDO para que EscritorDatosBaseLaravel —el escritor real—
 * escriba en la base en vez de anotar. Lo único que se carga antes de llamarlo es el
 * argumento y las opciones que carga Artisan al parsear `--todos`.
 */
function correrComandoDatosBase(\PDO $pdo)
{
    \Illuminate\Support\Facades\DB::usarPdo($pdo);

    $comando = new \App\Console\Commands\ImportAfipDatosBase();
    $comando->valoresArgumentos = ['catalogo' => null];
    $comando->valoresOpciones = ['dir' => null, 'file' => null, 'dry-run' => false, 'todos' => true];

    try {
        $codigo = $comando->handle();
    } finally {
        // Volver a modo grabador: si quedara en PDO, el próximo seeder escribiría
        // directo y además quedaría anotado para replicarse dos veces.
        \Illuminate\Support\Facades\DB::usarGrabador();
    }

    return ['codigo' => $codigo, 'salida' => $comando->salida, 'tablas' => $comando->tablas];
}

/**
 * Imprime el informe del comando: la tabla por catálogo y las líneas de detalle.
 *
 * @param  array<string, mixed>  $corrida
 */
function informarImportacion(array $corrida)
{
    foreach ($corrida['tablas'] as $tabla) {
        echo '  ' . implode(' | ', $tabla['encabezados']) . "\n";

        foreach ($tabla['filas'] as $fila) {
            echo '  ' . implode(' | ', array_map('strval', $fila)) . "\n";
        }
    }

    foreach ($corrida['salida'] as $linea) {
        if ($linea[0] === 'table') {
            continue;
        }

        printf("  [%s] %s\n", $linea[0], $linea[1]);
    }

    echo "  código de salida: {$corrida['codigo']}\n";
}

$primera = correrComandoDatosBase($pdo);
informarImportacion($primera);

echo "\n== Estado DESPUÉS ==\n";
$despues = Estado::capturar($pdo);

foreach ($despues['conteos'] as $tabla => $n) {
    $previo = $antes['conteos'][$tabla];
    $marca = $previo === $n ? '   ' : sprintf('%+d ', $n - $previo);

    printf("  %-30s %-8s %-8s %s\n", $tabla, $previo === null ? '-' : $previo, $n === null ? '-' : $n, $marca);
}

// ---------------------------------------------------------------------------
// 7) Idempotencia: segunda corrida del importador
// ---------------------------------------------------------------------------
echo "\n== 7) Idempotencia del importador (segunda corrida) ==\n";

$antesSegunda = Estado::capturar($pdo);
$segunda = correrComandoDatosBase($pdo);
$despuesSegunda = Estado::capturar($pdo);

$resumenSegunda = null;

foreach ($segunda['salida'] as $linea) {
    if ($linea[0] === 'info' && strpos($linea[1], 'Leídos') !== false) {
        $resumenSegunda = $linea[1];
    }
}

echo '  ' . ($resumenSegunda === null ? '(sin resumen)' : $resumenSegunda) . "\n";

echo "\n  informe completo de la segunda corrida:\n";
informarImportacion($segunda);

$cambios = [];

foreach ($antesSegunda['conteos'] as $tabla => $n) {
    if ($n !== $despuesSegunda['conteos'][$tabla]) {
        $cambios[] = "{$tabla}: {$n} -> {$despuesSegunda['conteos'][$tabla]}";
    }
}

echo '  cambios de conteos entre la 1ª y la 2ª corrida: ' . ($cambios === [] ? 'ninguno' : implode(', ', $cambios)) . "\n";

// ---------------------------------------------------------------------------
// Resumen de lo pedido
// ---------------------------------------------------------------------------
echo "\n== vouchers ==\n";
echo "  filas: {$despues['conteos']['vouchers']}\n";
echo '  por clase/inscription_id: ' . implode('  ', $despues['vouchers_por_clase']) . "\n";
echo '  distribución inscription_id: ' . json_encode($despues['vouchers_inscripcion']) . "\n";
echo '  A (001-005) y B (006-010) y C (011-016): ' . json_encode($despues['vouchers_016']) . "\n";

echo "\n== states.cot_code ==\n";
echo "  con cot_code: {$despues['states_cot_code']}\n";
echo "  con codigo31662: {$despues['states_codigo31662']}\n";

echo "\n== migrations ==\n";
echo "  total: {$despues['conteos']['migrations']}  duplicadas: {$despues['migrations_duplicadas']}\n";

echo "\n== vouchers: ids que cambiaron respecto de antes ==\n";
$nuevos = array_diff($despues['vouchers_ids'], $antes['vouchers_ids']);
$perdidos = array_diff($antes['vouchers_ids'], $despues['vouchers_ids']);
echo '  antes: ' . count($antes['vouchers_ids']) . ' ids (' . min($antes['vouchers_ids']) . '..' . max($antes['vouchers_ids']) . ")\n";
echo '  después: ' . count($despues['vouchers_ids']) . ' ids (' . min($despues['vouchers_ids']) . '..' . max($despues['vouchers_ids']) . ")\n";
echo '  ids nuevos: ' . (count($nuevos) ? implode(',', array_slice($nuevos, 0, 20)) : '(ninguno)') . "\n";
echo '  ids perdidos: ' . (count($perdidos) ? implode(',', $perdidos) : '(ninguno)') . "\n";

file_put_contents(
    __DIR__ . '/ultimo-estado.json',
    json_encode(['antes' => $antes, 'despues' => $despues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

echo "\nEstado completo en tools/seed-por-pdo/ultimo-estado.json\n";
