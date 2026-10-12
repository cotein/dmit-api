<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use PDO;
use InvalidArgumentException;

/**
 * Escritura de los parámetros de ARCA: tabla cruda afip_parametros y catálogos destino.
 *
 * Por qué PDO pelado y no Eloquent: son cargas por lote (T_CALLE o E_ACTIVIDAD_RUBRO
 * traen miles de filas) donde lo único que importa es el INSERT/UPDATE masivo, y además
 * deja la sincronización completa ejecutable fuera del framework — que es como se la
 * verifica en esta máquina, donde artisan no puede usar MySQL (falta pdo_mysql en el
 * PHP 8 con el que arranca Laravel). El comando le pasa el PDO de la conexión de Laravel
 * (DB::connection()->getPdo()), así que en producción la conexión es la de siempre.
 *
 * Nada de lo que se escribe acá viene del pedido SOAP: son los datos del catálogo, que
 * no incluyen token ni firma.
 */
final class AlmacenPadron100
{
    /** Filas por sentencia: 6 columnas x 500 = 3.000 marcadores, bien lejos del tope de MySQL. */
    public const LOTE = 500;

    /** @var PDO */
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function tablaParametrosExiste(): bool
    {
        $sentencia = $this->pdo->query("SHOW TABLES LIKE 'afip_parametros'");

        return $sentencia !== false && $sentencia->fetchColumn() !== false;
    }

    /**
     * Parámetros crudos ya guardados de una colección.
     *
     * @return array<string, array<string, mixed>>  [parametro_id => ['descripcion'=>, 'atributos'=>]]
     */
    public function parametrosDe(string $coleccion): array
    {
        $sentencia = $this->pdo->prepare(
            'SELECT parametro_id, descripcion, atributos FROM afip_parametros WHERE collection_name = ?'
        );
        $sentencia->execute([$coleccion]);

        $existentes = [];

        foreach ($sentencia->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $atributos = json_decode((string) $fila['atributos'], true);

            $existentes[(string) $fila['parametro_id']] = [
                'descripcion' => $fila['descripcion'],
                'atributos' => is_array($atributos) ? $atributos : [],
            ];
        }

        return $existentes;
    }

    /**
     * Filas de un catálogo destino, por su clave.
     *
     * @return array<string, array<string, mixed>>  [clave => ['id'=>, 'nombre'=>]]
     */
    public function existentesDe(string $tabla): array
    {
        $columnas = $this->columnasDe($tabla);

        $sentencia = $this->pdo->query(
            'SELECT id, `' . $columnas['clave'] . '` AS clave, `' . $columnas['nombre'] . '` AS nombre FROM `' . $tabla . '`'
        );

        $existentes = [];

        foreach ($sentencia->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if ($fila['clave'] === null || (string) $fila['clave'] === '') {
                continue;
            }

            $existentes[(string) $fila['clave']] = [
                'id' => (int) $fila['id'],
                'nombre' => $fila['nombre'] === null ? '' : (string) $fila['nombre'],
            ];
        }

        return $existentes;
    }

    /**
     * Provincias del sistema, para contrastar contra SUPA.E_PROVINCIA.
     *
     * @return array<string, array<string, mixed>>  [afip_code => ['id'=>, 'nombre'=>, 'cot_code'=>]]
     */
    public function provinciasExistentes(): array
    {
        $sentencia = $this->pdo->query(
            'SELECT id, afip_code, name, cot_code FROM states WHERE afip_code IS NOT NULL ORDER BY afip_code'
        );

        $existentes = [];

        foreach ($sentencia->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $existentes[(string) $fila['afip_code']] = [
                'id' => (int) $fila['id'],
                'nombre' => (string) $fila['name'],
                'cot_code' => (string) $fila['cot_code'],
            ];
        }

        return $existentes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas  Salida de ComparadorPadron100::compararParametros()
     */
    public function insertarParametros(array $filas): int
    {
        $escritas = 0;

        foreach (array_chunk($filas, self::LOTE) as $lote) {
            $marcadores = [];
            $valores = [];

            foreach ($lote as $fila) {
                $marcadores[] = '(?, ?, ?, ?, ?, ?, ?)';

                // created_at y updated_at igual a sincronizado_at: es el momento en que
                // el registro entró al sistema desde ARCA.
                $valores[] = $fila['collection_name'];
                $valores[] = $fila['parametro_id'];
                $valores[] = $fila['descripcion'];
                $valores[] = $fila['atributos'];
                $valores[] = $fila['sincronizado_at'];
                $valores[] = $fila['sincronizado_at'];
                $valores[] = $fila['sincronizado_at'];
            }

            // El ON DUPLICATE KEY hace idempotente hasta el caso raro de dos corridas
            // simultáneas sobre la misma colección (la clave única es la colección + el id).
            $sql = 'INSERT INTO afip_parametros (collection_name, parametro_id, descripcion, atributos, sincronizado_at, created_at, updated_at)'
                . ' VALUES ' . implode(', ', $marcadores)
                . ' ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion), atributos = VALUES(atributos),'
                . ' sincronizado_at = VALUES(sincronizado_at), updated_at = VALUES(updated_at)';

            $sentencia = $this->pdo->prepare($sql);
            $sentencia->execute($valores);
            $escritas += count($lote);
        }

        return $escritas;
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    public function actualizarParametros(array $filas): int
    {
        if ($filas === []) {
            return 0;
        }

        $sentencia = $this->pdo->prepare(
            'UPDATE afip_parametros SET descripcion = ?, atributos = ?, sincronizado_at = ?, updated_at = ?'
            . ' WHERE collection_name = ? AND parametro_id = ?'
        );

        $actualizadas = 0;

        foreach ($filas as $fila) {
            $sentencia->execute([
                $fila['descripcion'],
                $fila['atributos'],
                $fila['sincronizado_at'],
                $fila['sincronizado_at'],
                $fila['collection_name'],
                $fila['parametro_id'],
            ]);

            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * Alta de códigos nuevos en un catálogo destino.
     *
     * @param  array<string, string>  $filas  [codigo => nombre]
     */
    public function insertarDestino(string $tabla, array $filas, string $momento): int
    {
        if ($filas === []) {
            return 0;
        }

        $columnas = $this->columnasDe($tabla);
        $marcadores = [];
        $valores = [];

        foreach ($filas as $codigo => $nombre) {
            $marcadores[] = '(?, ?, ?, ?)';
            $valores[] = (string) $codigo;
            $valores[] = $nombre;
            $valores[] = $momento;
            $valores[] = $momento;
        }

        $sql = 'INSERT INTO `' . $tabla . '` (`' . $columnas['clave'] . '`, `' . $columnas['nombre'] . '`, created_at, updated_at)'
            . ' VALUES ' . implode(', ', $marcadores);

        $this->pdo->prepare($sql)->execute($valores);

        return count($filas);
    }

    /**
     * @param  array<string, string>  $filas  [codigo => nombre]
     */
    public function actualizarDestino(string $tabla, array $filas, string $momento): int
    {
        if ($filas === []) {
            return 0;
        }

        $columnas = $this->columnasDe($tabla);

        $sentencia = $this->pdo->prepare(
            'UPDATE `' . $tabla . '` SET `' . $columnas['nombre'] . '` = ?, updated_at = ? WHERE `' . $columnas['clave'] . '` = ?'
        );

        $actualizadas = 0;

        foreach ($filas as $codigo => $nombre) {
            $sentencia->execute([$nombre, $momento, (string) $codigo]);
            $actualizadas++;
        }

        return $actualizadas;
    }

    /**
     * @return array<string, string>
     */
    private function columnasDe(string $tabla): array
    {
        $tablas = DestinosPadron100::tablasEscribibles();

        if (! isset($tablas[$tabla])) {
            throw new InvalidArgumentException("La tabla {$tabla} no está declarada como destino escribible.");
        }

        return $tablas[$tabla];
    }
}
