<?php

/**
 * Verifica que la base LOCAL tenga las garantías que la lógica del corte de planes
 * da por sentadas.
 *
 * POR QUÉ EXISTE: el gating apoya la idempotencia y la concurrencia en índices únicos
 * (una suscripción por CUIT, una fila por empresa/mes/recurso, una reserva por clave
 * de reintento) y en las claves foráneas del catálogo. Si esos índices no estuvieran,
 * el código igual compilaría, las pruebas felices pasarían y un reintento podría
 * consumir dos lugares del contador. Esto es lo que lo vuelve verificable.
 *
 * TODO CORRE DENTRO DE UNA TRANSACCIÓN QUE SE REVIERTE AL FINAL: no deja una sola
 * fila. Los datos que usa son ids ficticios (999999 en adelante).
 *
 * Uso:  php7.4 tools/plan-por-pdo/verificar-invariantes.php
 */

require __DIR__ . '/../seed-por-pdo/Conexion.php';

use Tools\SeedPorPdo\Conexion;

$raiz = dirname(__DIR__, 2);
$pdo = Conexion::abrir($raiz);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ok = 0;
$fallas = 0;

/**
 * Corre una comprobación y espera que falle o que pase.
 */
function comprobar(string $titulo, bool $esperaFallo, callable $prueba): void
{
    global $ok, $fallas;

    try {
        $prueba();

        if ($esperaFallo) {
            echo "  FALLA  {$titulo}: se esperaba un rechazo y la base lo aceptó.\n";
            $fallas++;

            return;
        }

        echo "  OK     {$titulo}\n";
        $ok++;
    } catch (PDOException $e) {
        if ($esperaFallo) {
            echo "  OK     {$titulo} (rechazado como corresponde)\n";
            $ok++;

            return;
        }

        echo "  FALLA  {$titulo}: {$e->getMessage()}\n";
        $fallas++;
    }
}

$planInterno = (int) $pdo->query("SELECT id FROM plans WHERE code = 'internal'")->fetchColumn();
$planFree = (int) $pdo->query("SELECT id FROM plans WHERE code = 'free'")->fetchColumn();

if ($planInterno === 0 || $planFree === 0) {
    echo "No está cargado el catálogo: corré primero generar-sql.php + aplicar-sql.php.\n";
    exit(1);
}

echo "Invariantes de esquema del corte de planes\n\n";

$pdo->beginTransaction();

comprobar('una sola suscripción por CUIT', true, function () use ($pdo, $planFree) {
    $pdo->exec("INSERT INTO company_plans (company_id, plan_id, status, created_at, updated_at) VALUES (999901, {$planFree}, 'active', NOW(), NOW())");
    $pdo->exec("INSERT INTO company_plans (company_id, plan_id, status, created_at, updated_at) VALUES (999901, {$planFree}, 'active', NOW(), NOW())");
});

comprobar('un solo contador por empresa, mes y recurso', true, function () use ($pdo) {
    $pdo->exec("INSERT INTO plan_usage_counters (company_id, period, resource, used, created_at, updated_at) VALUES (999902, '2026-12', 'invoices', 1, NOW(), NOW())");
    $pdo->exec("INSERT INTO plan_usage_counters (company_id, period, resource, used, created_at, updated_at) VALUES (999902, '2026-12', 'invoices', 1, NOW(), NOW())");
});

comprobar('una sola reserva por clave de reintento', true, function () use ($pdo) {
    $pdo->exec("INSERT INTO plan_usage_reservations (company_id, period, resource, key_hash, status, expires_at, created_at, updated_at) VALUES (999903, '2026-12', 'invoices', 'hash-repetido', 'reserved', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())");
    $pdo->exec("INSERT INTO plan_usage_reservations (company_id, period, resource, key_hash, status, expires_at, created_at, updated_at) VALUES (999903, '2026-12', 'invoices', 'hash-repetido', 'reserved', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())");
});

comprobar('la misma clave en OTRO recurso sí entra (facturas y remitos son contadores distintos)', false, function () use ($pdo) {
    $pdo->exec("INSERT INTO plan_usage_reservations (company_id, period, resource, key_hash, status, expires_at, created_at, updated_at) VALUES (999903, '2026-12', 'remitos', 'hash-repetido', 'reserved', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())");
});

comprobar('no se puede cargar un tope de un plan inexistente', true, function () use ($pdo) {
    $pdo->exec("INSERT INTO plan_limits (plan_id, resource, limit_value, created_at, updated_at) VALUES (999999, 'invoices', 5, NOW(), NOW())");
});

comprobar('borrar un plan borra sus topes y funciones (cascade)', false, function () use ($pdo) {
    $pdo->exec("INSERT INTO plans (code, name, description, position, is_internal, price_amount, price_currency, is_active, created_at, updated_at) VALUES ('temporal', 'Temporal', NULL, 50, 0, 0, 'ARS', 1, NOW(), NOW())");
    $id = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO plan_limits (plan_id, resource, limit_value, created_at, updated_at) VALUES ({$id}, 'invoices', 7, NOW(), NOW())");
    $pdo->exec("INSERT INTO plan_features (plan_id, feature, created_at, updated_at) VALUES ({$id}, 'logo', NOW(), NOW())");
    $pdo->exec("DELETE FROM plans WHERE id = {$id}");

    $topes = (int) $pdo->query("SELECT COUNT(*) FROM plan_limits WHERE plan_id = {$id}")->fetchColumn();
    $funciones = (int) $pdo->query("SELECT COUNT(*) FROM plan_features WHERE plan_id = {$id}")->fetchColumn();

    if ($topes !== 0 || $funciones !== 0) {
        throw new PDOException('quedaron filas huérfanas del plan borrado');
    }
});

comprobar('el plan interno no tiene ningún tope (sin límite de verdad)', false, function () use ($pdo, $planInterno) {
    $topes = $pdo->query("SELECT limit_value FROM plan_limits WHERE plan_id = {$planInterno}")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($topes as $tope) {
        if ($tope !== null) {
            throw new PDOException('el plan interno tiene un tope cargado: ' . $tope);
        }
    }
});

// Esta consulta NO se revierte con la transacción: es la lectura de control del
// backfill, la que decide si se puede encender el gating.
$pendientes = (int) $pdo->query('SELECT COUNT(*) FROM companies c LEFT JOIN company_plans cp ON cp.company_id = c.id WHERE cp.id IS NULL')->fetchColumn();
$empresas = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();

$pdo->rollBack();

echo "\nConsultas de control de la puesta en marcha:\n";
echo "  empresas: {$empresas} · sin plan asignado: {$pendientes}\n";
echo "  plan interno cargado: " . ($planInterno > 0 ? 'sí' : 'NO') . " · plan gratuito cargado: " . ($planFree > 0 ? 'sí' : 'NO') . "\n";

echo "\nResultado: {$ok} comprobaciones OK · {$fallas} fallas\n";

exit($fallas === 0 ? 0 : 1);
