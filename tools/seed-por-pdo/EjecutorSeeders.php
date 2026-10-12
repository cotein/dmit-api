<?php

namespace Tools\SeedPorPdo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta los seeders REALES del proyecto y devuelve lo que pidieron escribir.
 *
 * POR QUÉ ASÍ Y NO LEYENDO EL TEXTO: leer los arrays con expresiones regulares
 * obliga a reimplementar el parser de PHP y a copiar los datos a un script aparte,
 * que es exactamente lo que se quiere evitar (el seeder deja de ser la única
 * fuente de verdad al primer cambio). Acá se hace `require` del archivo y se llama
 * a su `run()`; lo único simulado es el destino de DB::table(), que anota.
 *
 * El ORDEN no se decide acá: se le pregunta a DatabaseSeeder, que es quien hace
 * los `$this->call(...)` en secuencia. Un seeder agregado mañana corre solo.
 */
final class EjecutorSeeders
{
    /** @var string */
    private $raiz;

    public function __construct($raiz)
    {
        $this->raiz = rtrim($raiz, '/');
    }

    public function directorioSeeders()
    {
        return $this->raiz . '/database/seeders';
    }

    /**
     * Corre DatabaseSeeder y devuelve las altas que hace él mismo y los seeders
     * que pide, en el orden en que los pide.
     *
     * @return array{altas: array<int, array<string, mixed>>, seeders: array<int, string>}
     */
    public function correrDatabaseSeeder()
    {
        DB::usarGrabador();
        DB::reset();
        Seeder::$llamadas = [];

        require_once $this->directorioSeeders() . '/DatabaseSeeder.php';

        $clase = 'Database\\Seeders\\DatabaseSeeder';
        $instancia = new $clase();
        $instancia->run();

        return [
            'altas' => DB::$operaciones,
            'seeders' => Seeder::$llamadas,
        ];
    }

    /**
     * Corre un seeder por su clase y devuelve sus operaciones.
     *
     * @param  string  $clase  ej. "Database\Seeders\AfipVouchersTableSeeder"
     * @return array{archivo: string, operaciones: array<int, array<string, mixed>>}
     */
    public function correr($clase)
    {
        $corto = preg_replace('/^.*\\\\/', '', $clase);
        $archivo = $this->directorioSeeders() . '/' . $corto . '.php';

        if (! is_file($archivo)) {
            throw new \RuntimeException("DatabaseSeeder pide {$clase} y no existe {$archivo}.");
        }

        DB::usarGrabador();
        DB::reset();

        require_once $archivo;

        if (! class_exists($clase)) {
            throw new \RuntimeException("El archivo {$archivo} no declara la clase {$clase}.");
        }

        $instancia = new $clase();
        $instancia->run();

        return ['archivo' => $archivo, 'operaciones' => DB::$operaciones];
    }

    /**
     * ¿El seeder deja la tabla vacía y la vuelve a llenar?
     *
     * Se usa sólo para informar: es la diferencia entre "este seeder reemplaza el
     * catálogo" y "este seeder agrega filas encima de lo que ya hay".
     *
     * @param  array<int, array<string, mixed>>  $operaciones
     * @return array<string, string> tabla => truncate|delete|solo-altas
     */
    public static function efectoPorTabla(array $operaciones)
    {
        $efecto = [];

        foreach ($operaciones as $operacion) {
            $tabla = $operacion['tabla'];

            if ($operacion['op'] === 'truncate') {
                $efecto[$tabla] = 'truncate';
                continue;
            }

            if ($operacion['op'] === 'delete' && ! isset($efecto[$tabla])) {
                $efecto[$tabla] = 'delete';
                continue;
            }

            if (! isset($efecto[$tabla])) {
                $efecto[$tabla] = 'solo-altas';
            }
        }

        return $efecto;
    }
}
