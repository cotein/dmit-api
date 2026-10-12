<?php

namespace Tools\SeedPorPdo;

/**
 * Traduce las operaciones que anotó ReceptorTabla a SQL real, por PDO.
 *
 * POR QUÉ TRUNCATE NO ES UN exec() Y LISTO: MySQL rechaza TRUNCATE sobre una
 * tabla referenciada por una clave foránea (error 1701) aunque la tabla que la
 * referencia esté vacía. En esta base eso pasa con vouchers (orders.voucher_id),
 * ivas (order_items.iva_id) y accounting_accounts (products / sale_invoice_items).
 * Laravel emite un `truncate table` pelado, así que el seeder tal cual fallaría.
 *
 * La salida NO es desactivar el chequeo y seguir: antes de hacerlo se verifica que
 * NINGUNA tabla que referencia esté con filas. Si alguna tiene datos, se aborta en
 * vez de dejar filas huérfanas apuntando a un comprobante o a una cuenta que ya no
 * existe. La desviación respecto de Laravel queda registrada en el informe.
 */
final class AplicadorOperaciones
{
    /** @var \PDO */
    private $pdo;

    /** @var array<int, string> */
    private $avisos = [];

    /** @var array<string, int> filas escritas por tabla */
    private $escritas = [];

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function avisos()
    {
        return $this->avisos;
    }

    public function escritas()
    {
        return $this->escritas;
    }

    /**
     * Ejecuta una lista de operaciones en orden y devuelve cuántas filas insertó.
     *
     * @param  array<int, array<string, mixed>>  $operaciones
     * @return int
     */
    public function aplicar(array $operaciones)
    {
        $insertadas = 0;

        foreach ($operaciones as $operacion) {
            switch ($operacion['op']) {
                case 'truncate':
                    $this->truncar($operacion['tabla']);
                    break;

                case 'delete':
                    $borradas = $this->pdo->exec('DELETE FROM `' . $operacion['tabla'] . '`');
                    $this->avisos[] = "DELETE FROM {$operacion['tabla']}: {$borradas} fila(s)";
                    break;

                case 'insert':
                    $insertadas += $this->insertar($operacion['tabla'], $operacion['filas'], isset($operacion['ignorar']) && $operacion['ignorar']);
                    break;

                default:
                    throw new \RuntimeException('Operación desconocida: ' . $operacion['op']);
            }
        }

        return $insertadas;
    }

    /**
     * Vacia una tabla, sorteando la restricción de claves foráneas si es seguro.
     */
    private function truncar($tabla)
    {
        if (! $this->existeTabla($tabla)) {
            throw new \RuntimeException("El seeder pide truncar {$tabla}, que no existe en la base.");
        }

        try {
            $this->pdo->exec('TRUNCATE TABLE `' . $tabla . '`');
        } catch (\PDOException $e) {
            $esFkBloqueante = strpos($e->getMessage(), '1701') !== false;

            if (! $esFkBloqueante) {
                throw $e;
            }

            $conFilas = $this->referentesConFilas($tabla);

            if ($conFilas !== []) {
                throw new \RuntimeException(
                    "No se puede truncar {$tabla}: la referencian tablas con datos (" . implode(', ', $conFilas) . '). '
                    . 'Desactivar el chequeo de claves foráneas acá dejaría esas filas huérfanas.'
                );
            }

            // Ninguna tabla que la referencia tiene filas: desactivar el chequeo
            // no puede dejar huérfanos, y es lo que el TRUNCATE de Laravel habría
            // hecho si MySQL no lo bloqueara.
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            try {
                $this->pdo->exec('TRUNCATE TABLE `' . $tabla . '`');
            } finally {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }

            $this->avisos[] = "TRUNCATE {$tabla}: MySQL lo bloqueaba por clave foránea (1701) y las tablas que la referencian están vacías, así que se hizo con FOREIGN_KEY_CHECKS = 0.";
        }
    }

    /**
     * Inserta filas agrupadas por juego de columnas.
     *
     * Laravel arma un INSERT de varias filas por llamada; acá se agrupa por
     * columnas porque un mismo insert() podría —hoy no pasa— traer filas con
     * columnas distintas, y armar el INSERT con la unión las dejaría con NULL en
     * las que no traían.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return int
     */
    private function insertar($tabla, array $filas, $ignorar)
    {
        if ($filas === []) {
            return 0;
        }

        $grupos = [];

        foreach ($filas as $fila) {
            $grupos[implode("\x1f", array_keys($fila))][] = $fila;
        }

        $total = 0;

        foreach ($grupos as $grupo) {
            foreach (array_chunk($grupo, 200) as $lote) {
                $columnas = array_keys($lote[0]);
                $marcadores = '(' . implode(', ', array_fill(0, count($columnas), '?')) . ')';

                $sql = 'INSERT ' . ($ignorar ? 'IGNORE ' : '') . 'INTO `' . $tabla . '` ('
                    . implode(', ', array_map(function ($c) {
                        return '`' . $c . '`';
                    }, $columnas))
                    . ') VALUES ' . implode(', ', array_fill(0, count($lote), $marcadores));

                $parametros = [];

                foreach ($lote as $fila) {
                    foreach ($columnas as $columna) {
                        $parametros[] = $fila[$columna];
                    }
                }

                $sentencia = $this->pdo->prepare($sql);
                $sentencia->execute($parametros);

                $total += $sentencia->rowCount();
            }
        }

        $this->escritas[$tabla] = (isset($this->escritas[$tabla]) ? $this->escritas[$tabla] : 0) + $total;

        return $total;
    }

    private function existeTabla($tabla)
    {
        $st = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $st->execute([$tabla]);

        return (int) $st->fetchColumn() > 0;
    }

    /**
     * Tablas que referencian a $tabla por clave foránea y que hoy tienen filas.
     *
     * @return array<int, string>
     */
    private function referentesConFilas($tabla)
    {
        $st = $this->pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ?
             GROUP BY TABLE_NAME'
        );
        $st->execute([$tabla]);

        $conFilas = [];

        foreach ($st->fetchAll(\PDO::FETCH_COLUMN) as $referente) {
            $n = (int) $this->pdo->query('SELECT COUNT(*) FROM `' . $referente . '`')->fetchColumn();

            if ($n > 0) {
                $conFilas[] = $referente . " ({$n})";
            }
        }

        return $conFilas;
    }
}
