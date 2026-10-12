<?php

namespace Tools\SeedPorPdo;

/**
 * Mini constructor de consultas que reemplaza a Illuminate\Database\Query\Builder
 * cuando la fachada DB está en modo PDO.
 *
 * POR QUÉ EXISTE: `ImportAfipDatosBase::handle()` arma su escritor con
 * `new EscritorDatosBaseLaravel()`, y ese escritor habla con la base a través de la
 * fachada DB. Si se reemplaza la fachada por esta versión que EJECUTA contra PDO en
 * vez de anotar, el comando real y su escritor real corren enteros sin Laravel: no
 * hay que reimplementar el bucle de catálogos ni la lista blanca de columnas, y una
 * edición futura del comando se refleja sola.
 *
 * Sólo implementa lo que el escritor usa: get(), insert(), update() con where de
 * igualdad. Cualquier otra cosa revienta con un mensaje claro en vez de devolver
 * algo vacío, que es cómo un importador termina informando "0 filas" sin decir por
 * qué.
 */
final class ConstructorConsulta
{
    /** @var \PDO */
    private $pdo;

    /** @var string */
    private $tabla;

    /** @var array<int, array<int, mixed>> */
    private $donde = [];

    public function __construct(\PDO $pdo, $tabla)
    {
        $this->pdo = $pdo;
        $this->tabla = $tabla;
    }

    /**
     * Igual que el builder: con dos argumentos asume '='.
     *
     * @return $this
     */
    public function where($columna, $operador = null, $valor = null)
    {
        if (func_num_args() === 2) {
            $valor = $operador;
            $operador = '=';
        }

        if ($operador !== '=') {
            throw new \RuntimeException("El runner PDO sólo soporta where con '='; llegó '{$operador}'.");
        }

        $this->donde[] = [$columna, $valor];

        return $this;
    }

    /**
     * Devuelve todas las filas como arreglos asociativos.
     *
     * El escritor real hace `(array) $fila` sobre cada resultado, así que un arreglo
     * ya cumple el contrato.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get()
    {
        return $this->pdo->query($this->sqlSelect())->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function insert(array $columnas)
    {
        [$nombres, $valores] = $this->normalizar($columnas);

        $sql = 'INSERT INTO `' . $this->tabla . '` ('
            . implode(', ', array_map(function ($c) {
                return '`' . $c . '`';
            }, $nombres))
            . ') VALUES (' . implode(', ', array_fill(0, count($nombres), '?')) . ')';

        $st = $this->pdo->prepare($sql);
        $st->execute($valores);
    }

    public function update(array $columnas)
    {
        [$nombres, $valores] = $this->normalizar($columnas);

        $sql = 'UPDATE `' . $this->tabla . '` SET '
            . implode(', ', array_map(function ($c) {
                return '`' . $c . '` = ?';
            }, $nombres))
            . $this->sqlWhere();

        foreach ($this->donde as $condicion) {
            $valores[] = $condicion[1];
        }

        $st = $this->pdo->prepare($sql);
        $st->execute($valores);
    }

    public function __call($metodo, $argumentos)
    {
        throw new \RuntimeException(
            "El runner PDO no soporta DB::table(...)->{$metodo}(). "
            . 'Si el comando empezó a usarlo, hay que extender ConstructorConsulta antes de confiar en la corrida.'
        );
    }

    private function sqlSelect()
    {
        return 'SELECT * FROM `' . $this->tabla . '`' . $this->sqlWhere();
    }

    private function sqlWhere()
    {
        if ($this->donde === []) {
            return '';
        }

        $partes = [];

        foreach ($this->donde as $condicion) {
            $partes[] = '`' . $condicion[0] . '` = ?';
        }

        return ' WHERE ' . implode(' AND ', $partes);
    }

    /**
     * Separa nombres y valores, y convierte lo que PDO no sabe enlazar.
     *
     * `now()` devuelve un objeto de fecha (es lo que hace Laravel), y los booleanos
     * vienen como true/false de los seeders: PDO no los acepta tal cual.
     *
     * @param  array<string, mixed>  $columnas
     * @return array{0: array<int, string>, 1: array<int, mixed>}
     */
    private function normalizar(array $columnas)
    {
        $nombres = [];
        $valores = [];

        foreach ($columnas as $columna => $valor) {
            $nombres[] = $columna;
            $valores[] = self::enlazable($valor);
        }

        return [$nombres, $valores];
    }

    public static function enlazable($valor)
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }

        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }

        return $valor;
    }
}
