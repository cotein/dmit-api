<?php

/**
 * Verificación de la corrida: los siete puntos que hay que poder contestar.
 *
 * POR QUÉ ES UN SCRIPT Y NO UNA CONSULTA SUELTA: el punto más delicado —si los
 * `id` que ya existían siguen apuntando al mismo comprobante— sólo se puede
 * contestar cruzando la tabla con el archivo de ARCA. Y ese cruce tiene un límite
 * que conviene que quede escrito y no en la cabeza de quien lo corrió: el mapeo
 * id→código anterior NO se guardó antes de truncar (se guardaron los conteos), así
 * que se reconstruye por el ORDEN del archivo. Ver la nota del punto 3.
 *
 * Uso:  php tools/seed-por-pdo/verificar.php
 */

$raiz = dirname(__DIR__, 2);

require __DIR__ . '/Conexion.php';
require __DIR__ . '/Estado.php';
require $raiz . '/app/Src/Afip/DatosBase/ConfiguracionDatosBase.php';
require $raiz . '/app/Src/Afip/DatosBase/LectorDatosBase.php';

use App\Src\Afip\DatosBase\ConfiguracionDatosBase;
use App\Src\Afip\DatosBase\LectorDatosBase;
use Tools\SeedPorPdo\Conexion;
use Tools\SeedPorPdo\Estado;

$pdo = Conexion::abrir($raiz);
$estado = Estado::capturar($pdo);

echo "== 1) Conteos finales ==\n";

foreach ($estado['conteos'] as $tabla => $n) {
    printf("  %-30s %s\n", $tabla, $n === null ? '(no existe)' : $n);
}

echo "\n== 2) vouchers ==\n";
echo "  filas: {$estado['conteos']['vouchers']}\n";
echo '  distribución inscription_id: ' . json_encode($estado['vouchers_inscripcion']) . "\n";
echo "  detalle de los códigos de una letra:\n";

$st = $pdo->query(
    "SELECT id, afip_code, inscription_id, name FROM vouchers
     WHERE afip_code BETWEEN '001' AND '020' ORDER BY afip_code"
);

foreach ($st as $fila) {
    printf(
        "    id=%-4s %s  inscription_id=%-5s %s\n",
        $fila['id'],
        $fila['afip_code'],
        $fila['inscription_id'] === null ? 'NULL' : $fila['inscription_id'],
        $fila['name']
    );
}

echo "\n== 3) códigos que sólo trae ARCA ==\n";

$soloArca = ['109', '991', '992', '993', '994', '995', '997'];
$in = "'" . implode("','", $soloArca) . "'";

foreach ($pdo->query("SELECT id, afip_code, name, inscription_id FROM vouchers WHERE afip_code IN ({$in}) ORDER BY afip_code") as $fila) {
    printf(
        "    id=%-4s %s  inscription_id=%-5s %s\n",
        $fila['id'],
        $fila['afip_code'],
        $fila['inscription_id'] === null ? 'NULL' : $fila['inscription_id'],
        $fila['name']
    );
}

$presentes = $pdo->query("SELECT COUNT(*) FROM vouchers WHERE afip_code IN ({$in})")->fetchColumn();
echo "  presentes: {$presentes} de " . count($soloArca) . "\n";

// El estado anterior a la corrida: 97 filas, ids 1..97, exactamente los códigos del
// archivo de ARCA en su orden de archivo. Eso es una DEDUCCIÓN (la tabla coincidía
// código a código con el archivo y no tenía huecos de id), no una medición: el mapeo
// id→código no se guardó antes de truncar.
$lectura = LectorDatosBase::leer(ConfiguracionDatosBase::catalogo('comprobantes'), sys_get_temp_dir() . '/arca-csv/TABLACOMPROBANTES.csv');
$codigosArca = array_column($lectura['filas'], 'afip_code');

$actual = [];
foreach ($pdo->query('SELECT id, afip_code FROM vouchers WHERE id <= 97') as $fila) {
    $actual[(int) $fila['id']] = $fila['afip_code'];
}

$iguales = 0;
$movidos = [];

foreach ($codigosArca as $i => $codigo) {
    $id = $i + 1;
    $ahora = isset($actual[$id]) ? $actual[$id] : '(no existe)';

    if ($ahora === $codigo) {
        $iguales++;
        continue;
    }

    $movidos[] = "id {$id}: {$codigo} -> {$ahora}";
}

echo "\n  de los 97 id que ya existían, siguen apuntando al MISMO código: {$iguales} de 97\n";
echo "  (comparado contra el orden del archivo de ARCA, que es el orden en que los había insertado el importador)\n";

foreach (array_slice($movidos, 0, 15) as $m) {
    echo "    {$m}\n";
}

if (count($movidos) > 15) {
    echo '    ... y ' . (count($movidos) - 15) . " más\n";
}

// ¿Alguna fila referencia vouchers? Es lo que decide si el remapeo de ids puede
// romper algo.
echo "\n  filas que referencian vouchers por clave foránea:\n";

$st = $pdo->query(
    "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'vouchers'"
);

foreach ($st as $fk) {
    $n = $pdo->query('SELECT COUNT(*) FROM `' . $fk['TABLE_NAME'] . '` WHERE `' . $fk['COLUMN_NAME'] . '` IS NOT NULL')->fetchColumn();
    printf("    %s.%s: %s fila(s)\n", $fk['TABLE_NAME'], $fk['COLUMN_NAME'], $n);
}

echo "\n== 4) states.cot_code ==\n";
echo "  con cot_code: {$estado['states_cot_code']}\n";
echo "  con codigo31662: {$estado['states_codigo31662']}\n";
echo "  sin cot_code: " . ($estado['conteos']['states'] - $estado['states_cot_code']) . "\n";

echo "\n== 5) los ocho catálogos nuevos ==\n";
$ocho = [
    'afip_paises', 'afip_actividades_f883', 'afip_unidades_medida', 'afip_otros_tributos',
    'afip_incoterms', 'afip_operacion_condicion_iva', 'afip_conceptos_incluidos', 'afip_idiomas',
];
$total = 0;

foreach ($ocho as $tabla) {
    printf("  %-30s %s\n", $tabla, $estado['conteos'][$tabla]);
    $total += (int) $estado['conteos'][$tabla];
}

echo "  TOTAL: {$total}\n";
echo "  afip_parametros (no lo toca nadie): {$estado['conteos']['afip_parametros']}\n";
echo "  cot_provincias: {$estado['conteos']['cot_provincias']}   cot_unidades_medida: {$estado['conteos']['cot_unidades_medida']}\n";

echo "\n== 6) migrations ==\n";
echo "  filas: {$estado['conteos']['migrations']}  duplicadas: {$estado['migrations_duplicadas']}\n";

$st = $pdo->query('SELECT COUNT(*) c, COUNT(DISTINCT migration) d FROM migrations')->fetch();
echo "  COUNT(*)={$st['c']}  COUNT(DISTINCT migration)={$st['d']}\n";
