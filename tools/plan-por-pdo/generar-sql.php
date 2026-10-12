<?php

/**
 * Genera el SQL del corte de planes a partir de los archivos REALES del proyecto:
 * las migraciones (que salen de los stubs de esquema, no de un DDL escrito aparte) y
 * la carga del catálogo (que sale de PlanCatalogDefinition, la misma definición que
 * usa el seeder de producción).
 *
 * POR QUÉ GENERA SQL EN VEZ DE APLICARLO: en esta máquina la API no puede correr
 * (pide PHP ^8.1) y el único PHP con conector de MySQL es el 7.4, que no entiende la
 * sintaxis del proyecto. Entonces el SQL lo genera el PHP 8.4 —que sí entiende el
 * código— y lo aplica el 7.4 —que sí entiende la base—. El puente es texto.
 *
 * Uso:
 *   php8.4 tools/plan-por-pdo/generar-sql.php > /tmp/plan.sql
 *   php7.4 tools/plan-por-pdo/aplicar-sql.php /tmp/plan.sql
 *
 * No imprime CREATE DATABASE ni USE: el aplicador ya está conectado a la base que
 * corresponde, que en esta máquina es la LOCAL.
 */

require __DIR__ . '/StubsDeEsquema.php';
require __DIR__ . '/StubsDeModelos.php';

use Illuminate\Support\Facades\Schema;

$raiz = dirname(__DIR__, 2);

Schema::$simular = true;
Schema::$pdo = null;

// La definición del catálogo referencia constantes de los modelos (códigos de plan y
// nombres de funciones): se cargan los modelos REALES con los stubs de Eloquent, así
// esos valores no se escriben dos veces.
require $raiz . '/app/Models/Plan.php';
require $raiz . '/app/Models/PlanFeature.php';
require $raiz . '/app/Src/Services/Plans/PlanCatalog.php';
require $raiz . '/app/Src/Services/Plans/PlanCatalogDefinition.php';

echo "-- Generado desde las migraciones y la definición del catálogo del proyecto.\n";
echo "-- NO editar a mano: se regenera con tools/plan-por-pdo/generar-sql.php\n\n";

$archivos = glob($raiz . '/database/migrations/2026_10_13_*.php');
sort($archivos);

foreach ($archivos as $archivo) {
    $nombre = basename($archivo, '.php');

    // El aplicador PDO lee estas marcas para anotar la migración en la tabla
    // `migrations`, igual que haría Artisan.
    echo "-- migrations: {$nombre}\n";

    /** @var \Illuminate\Database\Migrations\Migration $migracion */
    $migracion = require $archivo;
    $migracion->up();
}

echo "\n-- Catálogo de planes (idempotente)\n";

foreach (\App\Src\Services\Plans\PlanCatalogDefinition::plans() as $plan) {
    $descripcion = $plan['description'] === null ? 'NULL' : "'" . addslashes($plan['description']) . "'";
    $interno = $plan['is_internal'] ? 1 : 0;

    echo "INSERT INTO `plans` (`code`, `name`, `description`, `position`, `is_internal`, `price_amount`, `price_currency`, `is_active`, `created_at`, `updated_at`)\n";
    echo "VALUES ('{$plan['code']}', '" . addslashes($plan['name']) . "', {$descripcion}, {$plan['position']}, {$interno}, {$plan['price_amount']}, 'ARS', 1, NOW(), NOW())\n";
    echo "ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `position` = VALUES(`position`), `is_internal` = VALUES(`is_internal`), `price_amount` = VALUES(`price_amount`), `is_active` = 1;\n";

    foreach ($plan['limits'] as $resource => $tope) {
        $valor = $tope === null ? 'NULL' : (string) (int) $tope;

        echo "INSERT INTO `plan_limits` (`plan_id`, `resource`, `limit_value`, `created_at`, `updated_at`)\n";
        echo "SELECT id, '{$resource}', {$valor}, NOW(), NOW() FROM `plans` WHERE `code` = '{$plan['code']}'\n";
        echo "ON DUPLICATE KEY UPDATE `limit_value` = VALUES(`limit_value`);\n";
    }

    // Un recurso que este plan ya no define se borra: si quedara, el tope viejo
    // seguiría aplicándose sin que nadie lo vea en la pantalla.
    $recursos = array_map(static fn (string $r): string => "'" . $r . "'", array_keys($plan['limits']));

    echo "DELETE FROM `plan_limits` WHERE `plan_id` = (SELECT id FROM `plans` WHERE `code` = '{$plan['code']}') AND `resource` NOT IN (" . implode(', ', $recursos) . ");\n";

    foreach ($plan['features'] as $feature) {
        echo "INSERT INTO `plan_features` (`plan_id`, `feature`, `created_at`, `updated_at`)\n";
        echo "SELECT id, '{$feature}', NOW(), NOW() FROM `plans` WHERE `code` = '{$plan['code']}'\n";
        echo "ON DUPLICATE KEY UPDATE `feature` = VALUES(`feature`);\n";
    }

    if ($plan['features'] === []) {
        echo "DELETE FROM `plan_features` WHERE `plan_id` = (SELECT id FROM `plans` WHERE `code` = '{$plan['code']}');\n";
    } else {
        $features = array_map(static fn (string $f): string => "'" . $f . "'", $plan['features']);
        echo "DELETE FROM `plan_features` WHERE `plan_id` = (SELECT id FROM `plans` WHERE `code` = '{$plan['code']}') AND `feature` NOT IN (" . implode(', ', $features) . ");\n";
    }
}

echo "\n-- Control: planes cargados y empresas sin plan\n";
echo "SELECT COUNT(*) AS planes FROM `plans`;\n";
echo "SELECT p.code, l.resource, l.limit_value FROM `plans` p LEFT JOIN `plan_limits` l ON l.plan_id = p.id ORDER BY p.position, l.resource;\n";
echo "SELECT COUNT(*) AS empresas_sin_plan FROM `companies` c LEFT JOIN `company_plans` cp ON cp.company_id = c.id WHERE cp.id IS NULL;\n";
