<?php

/**
 * Aplica a la base LOCAL el SQL generado por generar-sql.php y anota las migraciones
 * en la tabla `migrations`, igual que haría Artisan.
 *
 * POR QUÉ ES UN SCRIPT APARTE Y EN PHP 7.4: el otro extremo del puente. El generador
 * necesita PHP 8.4 para entender el código del proyecto; este aplicador necesita
 * PHP 7.4 porque es el único de la máquina con pdo_mysql. El "puente" entre los dos
 * es un archivo de texto con SQL, no una copia del DDL.
 *
 * Uso:
 *   php7.4 tools/plan-por-pdo/aplicar-sql.php /tmp/plan.sql
 *   php7.4 tools/plan-por-pdo/aplicar-sql.php /tmp/plan.sql --simular
 *
 * Es idempotente para el catálogo (usa ON DUPLICATE KEY UPDATE) y saltea las
 * migraciones que ya figuren en `migrations`.
 *
 * OJO CON EL CONTEO: la tabla `migrations` de este proyecto ya tuvo filas duplicadas
 * por un `LIKE` mal armado, así que al final compara COUNT(*) contra
 * COUNT(DISTINCT migration) y falla ruidosamente si no coinciden.
 */

require __DIR__ . '/../seed-por-pdo/Conexion.php';

use Tools\SeedPorPdo\Conexion;

$archivoSql = $argv[1] ?? null;

if ($archivoSql === null || ! is_file($archivoSql)) {
    fwrite(STDERR, "Uso: php7.4 aplicar-sql.php <archivo.sql> [--simular]\n");
    exit(2);
}

$simular = in_array('--simular', $argv, true);
$raiz = dirname(__DIR__, 2);

$pdo = Conexion::abrir($raiz);

echo $simular ? "MODO SIMULACIÓN: no se escribe nada.\n" : "Aplicando {$archivoSql}\n";

// El SQL se parte por las marcas de migración: cada bloque pertenece a un archivo de
// database/migrations y se anota con su nombre.
$contenido = file_get_contents($archivoSql);
$lineas = explode("\n", (string) $contenido);

$batch = $simular ? 0 : (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn() + 1;

$migracionActual = null;
$buffer = [];
$aplicadas = 0;
$salteadas = 0;

$ejecutar = function (string $sql, ?string $migracion) use ($pdo, $simular, $batch, &$aplicadas, &$salteadas): void {
    $sql = trim($sql);

    if ($sql === '') {
        return;
    }

    if ($migracion !== null) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = ?');
        $st->execute([$migracion]);

        if ((int) $st->fetchColumn() > 0 && ! $simular) {
            echo "  ya aplicada: {$migracion}\n";
            $salteadas++;

            return;
        }
    }

    if ($simular) {
        echo "  [simulado] " . substr(preg_replace('/\s+/', ' ', $sql), 0, 90) . "...\n";

        return;
    }

    $pdo->exec($sql);

    if ($migracion !== null) {
        $st = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
        $st->execute([$migracion, $batch]);
        echo "  {$migracion}\n";
        $aplicadas++;
    }
};

foreach ($lineas as $linea) {
    if (strpos(trim($linea), '-- migrations:') === 0) {
        // Cierra el bloque anterior antes de abrir el nuevo.
        $ejecutar(implode("\n", $buffer), $migracionActual);
        $buffer = [];
        $migracionActual = trim(str_replace('-- migrations:', '', trim($linea)));
        continue;
    }

    // Los comentarios sueltos y las líneas vacías no se envían a la base.
    if (trim($linea) === '' || strpos(trim($linea), '--') === 0) {
        continue;
    }

    $buffer[] = $linea;

    // Las sentencias se ejecutan al cerrar con punto y coma, así el archivo puede
    // mezclar CREATE TABLE completos con sentencias sueltas del catálogo.
    if (substr(trim($linea), -1) === ';') {
        $sql = rtrim(trim(implode("\n", $buffer)), ';');
        $esMigracion = $migracionActual !== null && stripos($sql, 'CREATE TABLE') === 0;

        $ejecutar($sql, $esMigracion ? $migracionActual : null);
        $buffer = [];

        if ($esMigracion) {
            $migracionActual = null;
        }
    }
}

$ejecutar(implode("\n", $buffer), $migracionActual);

if (! $simular) {
    echo "\nBatch {$batch}: aplicadas {$aplicadas} migraciones · ya estaban {$salteadas}\n";
}

$conteo = $pdo->query('SELECT COUNT(*) c, COUNT(DISTINCT migration) d FROM migrations')->fetch(PDO::FETCH_ASSOC);

echo "migrations: filas={$conteo['c']} distintas={$conteo['d']}\n";

if ((int) $conteo['c'] !== (int) $conteo['d']) {
    echo "ATENCIÓN: hay filas duplicadas en `migrations`.\n";
    exit(1);
}

echo "\nTablas del corte:\n";

foreach (['plans', 'plan_limits', 'plan_features', 'company_plans', 'plan_usage_counters', 'plan_usage_reservations', 'plan_change_logs', 'plan_settings'] as $tabla) {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $st->execute([$tabla]);
    $existe = (int) $st->fetchColumn() > 0;
    $filas = $existe ? (int) $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn() : 0;

    echo '  ' . str_pad($tabla, 26) . ($existe ? "OK  ({$filas} filas)" : 'FALTA') . "\n";
}

exit(0);
