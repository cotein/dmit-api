<?php

/**
 * Stubs de las fachadas de esquema de Laravel, por PDO.
 *
 * POR QUÉ EXISTEN: en esta máquina `php artisan migrate` no puede correr (la app pide
 * PHP ^8.1 y el único PHP con pdo_mysql es el 7.4). Para poder aplicar las migraciones
 * en la base LOCAL sin escribir el DDL por segunda vez —que es como los dos caminos
 * terminan divergiendo— este archivo implementa lo mínimo que usan las migraciones de
 * este corte: Schema::create, Schema::dropIfExists, Schema::hasTable y las columnas
 * con sus modificadores.
 *
 * NO ES UN ORM: si una migración usa algo que no está acá, el stub falla en voz alta
 * con el nombre del método en vez de generar una tabla incompleta que después parezca
 * correcta.
 *
 * Los nombres de clase viven en el namespace de Laravel a propósito: este archivo se
 * carga ANTES que las migraciones y sin el autoload de vendor, así que PHP usa estas
 * definiciones y no las de verdad (que necesitan el driver de MySQL).
 */

namespace Illuminate\Database\Migrations {
    abstract class Migration
    {
        abstract public function up(): void;

        public function down(): void
        {
        }
    }
}

namespace Illuminate\Database\Schema {
    /**
     * Una columna, con sus modificadores. Arma su propio SQL al final, así el orden
     * de encadenado da igual: `string('x')->nullable()->default(null)` y
     * `string('x')->default(null)->nullable()` escriben lo mismo.
     */
    class ColumnDefinition
    {
        /** @var array<int, string> */
        private array $modificadores = [];

        public function __construct(private readonly string $columna, private readonly string $definicion)
        {
        }

        public function nullable(): self
        {
            $this->modificadores[] = 'NULL';

            return $this;
        }

        public function default(mixed $valor): self
        {
            if ($valor === null) {
                $this->modificadores[] = 'DEFAULT NULL';
            } elseif (is_bool($valor)) {
                $this->modificadores[] = 'DEFAULT ' . ($valor ? '1' : '0');
            } elseif (is_int($valor) || is_float($valor)) {
                $this->modificadores[] = 'DEFAULT ' . $valor;
            } else {
                $this->modificadores[] = "DEFAULT '" . addslashes((string) $valor) . "'";
            }

            return $this;
        }

        public function useCurrent(): self
        {
            $this->modificadores[] = 'DEFAULT CURRENT_TIMESTAMP';

            return $this;
        }

        public function primary(): self
        {
            $this->modificadores[] = 'PRIMARY KEY';

            return $this;
        }

        public function unique(): self
        {
            $this->modificadores[] = 'UNIQUE';

            return $this;
        }

        public function sql(): string
        {
            $definicion = $this->definicion;

            // `nullable()` no puede convivir con el NOT NULL de la definición base:
            // sin esto salía "TEXT NOT NULL NULL", que MySQL rechaza.
            if (in_array('NULL', $this->modificadores, true)) {
                $definicion = str_replace(' NOT NULL', '', $definicion);
            }

            $partes = array_filter([$definicion, implode(' ', $this->modificadores)]);

            return '`' . $this->columna . '` ' . implode(' ', $partes);
        }
    }

    /**
     * Una clave foránea: se agrega como cláusula aparte, al final del CREATE TABLE.
     */
    class ForeignKeyDefinition
    {
        private string $columnaReferida = 'id';
        private string $tablaReferida = '';
        private string $onDelete = '';

        /**
         * `$tablaPropia` entra en el nombre del constraint a propósito: los nombres de
         * clave foránea son únicos en toda la base, así que dos tablas que apuntan a
         * `plans.id` con el mismo nombre harían fallar la segunda.
         */
        public function __construct(private readonly string $columna, private readonly string $tablaPropia)
        {
        }

        public function references(string $columna): self
        {
            $this->columnaReferida = $columna;

            return $this;
        }

        public function on(string $tabla): self
        {
            $this->tablaReferida = $tabla;

            return $this;
        }

        public function cascadeOnDelete(): self
        {
            $this->onDelete = 'ON DELETE CASCADE';

            return $this;
        }

        public function sql(): string
        {
            if ($this->tablaReferida === '') {
                throw new \RuntimeException('La clave foránea de `' . $this->columna . '` no dice a qué tabla apunta.');
            }

            return 'CONSTRAINT `fk_' . $this->tablaPropia . '_' . $this->columna . '` FOREIGN KEY (`'
                . $this->columna . '`) REFERENCES `' . $this->tablaReferida . '` (`' . $this->columnaReferida . '`) '
                . $this->onDelete;
        }
    }

    /**
     * Acumula columnas, índices y claves foráneas, y arma el CREATE TABLE.
     */
    class Blueprint
    {
        /** @var array<int, ColumnDefinition> */
        private array $columnas = [];

        /** @var array<int, string> */
        private array $indices = [];

        /** @var array<int, ForeignKeyDefinition> */
        private array $foraneas = [];

        public function __construct(private readonly string $tabla)
        {
        }

        public function id(string $columna = 'id'): ColumnDefinition
        {
            return $this->agregar($columna, 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY');
        }

        public function string(string $columna, int $largo = 255): ColumnDefinition
        {
            return $this->agregar($columna, 'VARCHAR(' . $largo . ') NOT NULL');
        }

        public function char(string $columna, int $largo = 1): ColumnDefinition
        {
            return $this->agregar($columna, 'CHAR(' . $largo . ') NOT NULL');
        }

        public function text(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'TEXT NOT NULL');
        }

        public function boolean(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'TINYINT(1) NOT NULL');
        }

        public function unsignedTinyInteger(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'TINYINT UNSIGNED NOT NULL');
        }

        public function unsignedSmallInteger(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'SMALLINT UNSIGNED NOT NULL');
        }

        public function unsignedInteger(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'INT UNSIGNED NOT NULL');
        }

        public function unsignedBigInteger(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'BIGINT UNSIGNED NOT NULL');
        }

        public function decimal(string $columna, int $total = 8, int $decimales = 2): ColumnDefinition
        {
            return $this->agregar($columna, 'DECIMAL(' . $total . ',' . $decimales . ') NOT NULL');
        }

        public function timestamp(string $columna): ColumnDefinition
        {
            return $this->agregar($columna, 'TIMESTAMP NOT NULL');
        }

        public function timestamps(): void
        {
            $this->agregar('created_at', 'TIMESTAMP NULL DEFAULT NULL');
            $this->agregar('updated_at', 'TIMESTAMP NULL DEFAULT NULL');
        }

        /**
         * @param  string|array<int, string>  $columnas
         */
        public function unique($columnas, ?string $nombre = null): void
        {
            $this->indices[] = $this->indice('UNIQUE', $columnas, $nombre);
        }

        /**
         * @param  string|array<int, string>  $columnas
         */
        public function index($columnas, ?string $nombre = null): void
        {
            $this->indices[] = $this->indice('INDEX', $columnas, $nombre);
        }

        public function foreign(string $columna): ForeignKeyDefinition
        {
            $foranea = new ForeignKeyDefinition($columna, $this->tabla);
            $this->foraneas[] = $foranea;

            return $foranea;
        }

        public function nombreDeTabla(): string
        {
            return $this->tabla;
        }

        public function sql(): string
        {
            $partes = [];

            foreach ($this->columnas as $columna) {
                $partes[] = $columna->sql();
            }

            foreach ($this->indices as $indice) {
                $partes[] = $indice;
            }

            foreach ($this->foraneas as $foranea) {
                $partes[] = $foranea->sql();
            }

            return 'CREATE TABLE `' . $this->tabla . "` (\n  " . implode(",\n  ", $partes)
                . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        private function agregar(string $columna, string $definicion): ColumnDefinition
        {
            $definicionColumna = new ColumnDefinition($columna, $definicion);
            $this->columnas[] = $definicionColumna;

            return $definicionColumna;
        }

        /**
         * @param  string|array<int, string>  $columnas
         */
        private function indice(string $tipo, $columnas, ?string $nombre): string
        {
            $columnas = is_array($columnas) ? $columnas : [$columnas];

            $nombreIndice = $nombre ?? ($this->tabla . '_' . implode('_', $columnas) . '_' . strtolower($tipo));

            $listado = implode(', ', array_map(static fn (string $c): string => '`' . $c . '`', $columnas));

            return $tipo . ' `' . $nombreIndice . '` (' . $listado . ')';
        }
    }
}

namespace Illuminate\Support\Facades {
    use Illuminate\Database\Schema\Blueprint;

    /**
     * Fachada Schema: sólo lo que usan las migraciones de este corte.
     */
    class Schema
    {
        /** @var \PDO|null */
        public static $pdo = null;

        /** @var bool */
        public static $simular = false;

        public static function create(string $tabla, \Closure $definicion): void
        {
            $blueprint = new Blueprint($tabla);
            $definicion($blueprint);

            $sql = $blueprint->sql();

            if (self::$simular) {
                echo $sql . ";\n\n";

                return;
            }

            self::pdo()->exec($sql);
        }

        public static function dropIfExists(string $tabla): void
        {
            if (self::$simular) {
                echo 'DROP TABLE IF EXISTS `' . $tabla . "`;\n\n";

                return;
            }

            self::pdo()->exec('DROP TABLE IF EXISTS `' . $tabla . '`');
        }

        public static function hasTable(string $tabla): bool
        {
            if (self::$simular) {
                return false;
            }

            $st = self::pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $st->execute([$tabla]);

            return (int) $st->fetchColumn() > 0;
        }

        private static function pdo(): \PDO
        {
            if (self::$pdo === null) {
                throw new \RuntimeException('Schema no tiene conexión: se llamó sin abrir PDO.');
            }

            return self::$pdo;
        }
    }
}
