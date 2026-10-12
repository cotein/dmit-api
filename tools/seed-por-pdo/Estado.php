<?php

namespace Tools\SeedPorPdo;

/**
 * Foto del estado de la base, para poder comparar antes y después.
 *
 * POR QUÉ SE TOMA ANTES: los seeders truncan, así que la diferencia contra el
 * estado previo es la única forma de saber qué se perdió y qué apareció. Sin la
 * foto de antes, "quedaron 102 comprobantes" no dice nada.
 *
 * Incluye a propósito las tablas que NO debería tocar nadie (los ocho catálogos de
 * ARCA, cot_provincias y cot_unidades_medida): si una de esas cambia, el número
 * tiene que saltar a la vista en vez de pasar como diferencia esperable.
 */
final class Estado
{
    /**
     * Conteos por tabla. Se separan los catálogos que este trabajo NO debe tocar.
     *
     * @var array<int, string>
     */
    private static $tablas = [
        // Catálogos de ARCA: los toca el seeder y el importador.
        'states', 'vouchers', 'afip_documents', 'moneys', 'afip_inscriptions',
        // Los ocho catálogos nuevos, que son de otro módulo.
        'afip_paises', 'afip_actividades_f883', 'afip_unidades_medida', 'afip_otros_tributos',
        'afip_incoterms', 'afip_operacion_condicion_iva', 'afip_conceptos_incluidos', 'afip_idiomas',
        // Almacén crudo del WS de padrón: ningún seeder lo toca.
        'afip_parametros',
        // Catálogos de COT.
        'cot_provincias', 'cot_unidades_medida',
        // Otras tablas que sí tocan los seeders.
        'ivas', 'banks', 'roles', 'permissions', 'accounting_accounts', 'payment_types',
        'sale_conditions', 'type_users', 'company_billing_concepts', 'company_types', 'statuses',
        // Referencias: si alguna deja de estar vacía, hay filas colgando de vouchers.
        'orders', 'sale_invoice_items', 'order_items', 'products',
        // Control de migraciones.
        'migrations',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function capturar(\PDO $pdo)
    {
        $conteos = [];

        foreach (self::$tablas as $tabla) {
            $conteos[$tabla] = self::existeTabla($pdo, $tabla)
                ? (int) $pdo->query('SELECT COUNT(*) FROM `' . $tabla . '`')->fetchColumn()
                : null;
        }

        $distribucion = [];
        $st = $pdo->query(
            "SELECT COALESCE(CAST(inscription_id AS CHAR), 'NULL') v, COUNT(*) n
             FROM vouchers GROUP BY v ORDER BY v"
        );

        foreach ($st as $fila) {
            $distribucion[$fila['v']] = (int) $fila['n'];
        }

        $aislados = [];
        $st = $pdo->query("SELECT id, afip_code FROM vouchers WHERE afip_code IN ('001','002','003','004','005','006','007','008','009','010','011','012','013','014','015','016') ORDER BY id");
        foreach ($st as $fila) {
            $aislados[$fila['id']] = $fila['afip_code'];
        }

        $porClase = [];
        $st = $pdo->query(
            "SELECT SUBSTRING(afip_code, 1, 1) clase, COALESCE(CAST(inscription_id AS CHAR), 'NULL') insc, COUNT(*) n
             FROM vouchers GROUP BY clase, insc ORDER BY clase, insc"
        );
        foreach ($st as $fila) {
            $porClase[] = $fila['clase'] . '/' . $fila['insc'] . '=' . $fila['n'];
        }

        $duplicadas = $pdo->query('SELECT COUNT(*) - COUNT(DISTINCT migration) FROM migrations')->fetchColumn();

        return [
            'conteos' => $conteos,
            'vouchers_inscripcion' => $distribucion,
            'vouchers_por_clase' => $porClase,
            'vouchers_016' => $aislados,
            'vouchers_ids' => array_map('intval', $pdo->query('SELECT id FROM vouchers ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN)),
            'codigos' => $pdo->query('SELECT afip_code FROM vouchers ORDER BY afip_code')->fetchAll(\PDO::FETCH_COLUMN),
            'states_cot_code' => (int) $pdo->query("SELECT COUNT(*) FROM states WHERE cot_code IS NOT NULL AND cot_code <> ''")->fetchColumn(),
            'states_codigo31662' => (int) $pdo->query("SELECT COUNT(*) FROM states WHERE codigo31662 IS NOT NULL AND codigo31662 <> ''")->fetchColumn(),
            'migrations_duplicadas' => (int) $duplicadas,
        ];
    }

    private static function existeTabla(\PDO $pdo, $tabla)
    {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $st->execute([$tabla]);

        return (int) $st->fetchColumn() > 0;
    }
}
