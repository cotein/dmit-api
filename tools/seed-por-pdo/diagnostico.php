<?php

/**
 * Diagnóstico previo: conexión, claves foráneas que apuntan a las tablas que los
 * seeders truncan y conteos actuales por tabla.
 *
 * POR QUÉ EXISTE: los seeders hacen TRUNCATE y MySQL rechaza TRUNCATE sobre una
 * tabla referenciada por una FK. Antes de truncar hay que saber si eso va a pasar,
 * y antes de tocar nada hay que tener los conteos para poder comparar después.
 */

require __DIR__ . '/Conexion.php';

use Tools\SeedPorPdo\Conexion;

$raiz = dirname(__DIR__, 2);
$pdo = Conexion::abrir($raiz);

echo "Conectado a ", $pdo->query('SELECT DATABASE()')->fetchColumn(), "\n\n";

echo "=== FKs que apuntan a tablas candidatas a TRUNCATE ===\n";
$sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY REFERENCED_TABLE_NAME, TABLE_NAME";
foreach ($pdo->query($sql) as $fila) {
    echo sprintf("  %-28s -> %s.%s\n", $fila['REFERENCED_TABLE_NAME'], $fila['TABLE_NAME'], $fila['COLUMN_NAME']);
}

echo "\n=== Conteos actuales ===\n";
$tablas = [
    'afip_paises', 'afip_actividades_f883', 'afip_unidades_medida', 'afip_otros_tributos',
    'afip_incoterms', 'afip_operacion_condicion_iva', 'afip_conceptos_incluidos', 'afip_idiomas',
    'afip_parametros', 'states', 'vouchers', 'afip_documents', 'moneys', 'afip_inscriptions',
    'cot_provincias', 'cot_unidades_medida', 'migrations',
    'sale_invoice_items', 'ivas', 'banks', 'roles', 'permissions', 'accounting_accounts',
    'payment_types', 'sale_conditions', 'type_users', 'company_billing_concepts',
];

foreach ($tablas as $tabla) {
    try {
        $n = $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
        echo sprintf("  %-28s %s\n", $tabla, $n);
    } catch (\PDOException $e) {
        echo sprintf("  %-28s (no existe)\n", $tabla);
    }
}

echo "\n=== vouchers.inscription_id ===\n";
$sql = "SELECT COALESCE(CAST(inscription_id AS CHAR), 'NULL') AS v, COUNT(*) n, MIN(id) min_id, MAX(id) max_id
        FROM vouchers GROUP BY v ORDER BY min_id";
foreach ($pdo->query($sql) as $fila) {
    echo sprintf("  inscription_id = %-5s n=%-4s ids %s..%s\n", $fila['v'], $fila['n'], $fila['min_id'], $fila['max_id']);
}

echo "\n=== ids de vouchers (huecos) ===\n";
$ids = $pdo->query('SELECT id FROM vouchers ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);
$faltan = array_diff(range(1, max($ids)), $ids);
echo "  total=" . count($ids) . " max=" . max($ids) . " faltantes=" . (count($faltan) ? implode(',', $faltan) : '(ninguno)') . "\n";

echo "\n=== migrations duplicadas ===\n";
$dup = $pdo->query('SELECT COUNT(*) c, COUNT(DISTINCT migration) d FROM migrations')->fetch();
echo "  COUNT(*)={$dup['c']} COUNT(DISTINCT)={$dup['d']}\n";

echo "\n=== states.cot_code ===\n";
echo "  con cot_code: " . $pdo->query("SELECT COUNT(*) FROM states WHERE cot_code IS NOT NULL AND cot_code <> ''")->fetchColumn() . "\n";
echo "  codigo31662 cargado: " . $pdo->query("SELECT COUNT(*) FROM states WHERE codigo31662 IS NOT NULL AND codigo31662 <> ''")->fetchColumn() . "\n";
