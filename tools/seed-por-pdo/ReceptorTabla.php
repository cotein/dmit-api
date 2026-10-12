<?php

namespace Tools\SeedPorPdo;

use Illuminate\Support\Facades\DB;

/**
 * Anota lo que un seeder le pide a la base, en vez de escribir.
 *
 * POR QUÉ NO ESCRIBE DIRECTO: los seeders se ejecutan enteros primero y recién
 * después se replica todo por PDO. Así el orden y el contenido salen del archivo
 * del seeder tal cual está —incluido el TRUNCATE y su lugar en la secuencia— y no
 * de una lista paralela que alguien tendría que mantener sincronizada.
 *
 * Las operaciones que no están soportadas lanzan excepción a propósito: un seeder
 * que mañana use un `where()` no puede terminar borrando la tabla entera en
 * silencio.
 */
final class ReceptorTabla
{
    /** @var string */
    private $tabla;

    public function __construct($tabla)
    {
        $this->tabla = $tabla;
    }

    public function truncate()
    {
        DB::$operaciones[] = ['op' => 'truncate', 'tabla' => $this->tabla];
    }

    public function delete()
    {
        DB::$operaciones[] = ['op' => 'delete', 'tabla' => $this->tabla];
    }

    public function insert($filas)
    {
        DB::$operaciones[] = [
            'op' => 'insert',
            'tabla' => $this->tabla,
            'filas' => self::normalizar($filas),
        ];
    }

    public function insertOrIgnore($filas)
    {
        DB::$operaciones[] = [
            'op' => 'insert',
            'tabla' => $this->tabla,
            'filas' => self::normalizar($filas),
            'ignorar' => true,
        ];
    }

    public function where($columna, $operador = null, $valor = null)
    {
        throw new \RuntimeException(
            "El seeder usa where({$columna}) sobre {$this->tabla}: el runner PDO no lo soporta. "
            . 'Extendé ReceptorTabla antes de correrlo, para no borrar la tabla entera sin querer.'
        );
    }

    public function updateOrInsert(array $atributos, array $valores = [])
    {
        throw new \RuntimeException(
            "El seeder usa updateOrInsert sobre {$this->tabla}: el runner PDO no lo soporta todavía."
        );
    }

    /**
     * Laravel acepta una fila suelta o una lista de filas en el mismo método; hay
     * que distinguirlas acá o la fila suelta se trataría como lista de columnas.
     *
     * @param  array<mixed>  $filas
     * @return array<int, array<string, mixed>>
     */
    private static function normalizar($filas)
    {
        if ($filas === []) {
            return [];
        }

        $primera = reset($filas);

        return is_array($primera) ? array_values($filas) : [$filas];
    }
}
