<?php

namespace Tools\SeedPorPdo;

/**
 * Stub de Illuminate\Database\Eloquent\Factories\Factory.
 *
 * DatabaseSeeder hace cinco altas con factorías (`CompanyTypeFactory::times(1)`,
 * `StatusFactory::times(1)`) y ésas también forman parte de `db:seed`: si no se
 * replican, el runner no estaría corriendo los seeders, estaría corriendo una
 * parte. Lo único que hace falta de la factoría real es saber a qué TABLA escribe
 * y con qué timestamps, porque los valores los pasa DatabaseSeeder literales.
 *
 * La tabla NO se escribe a mano: se deduce del archivo del modelo
 * (app/Models/<Nombre>.php) —de su `protected $table` si lo declara, y si no, del
 * plural en snake_case, que es la convención de Eloquent—. Así, si mañana se
 * renombra la tabla del modelo, el runner la sigue.
 */
abstract class FabricaStub
{
    /** @var int */
    private $cantidad = 1;

    public static function times($cantidad)
    {
        $instancia = new static();
        $instancia->cantidad = (int) $cantidad;

        return $instancia;
    }

    /**
     * Alta de $cantidad filas con los atributos que pasa DatabaseSeeder.
     *
     * Eloquent agrega created_at y updated_at en cualquier alta mientras el modelo
     * no tenga $timestamps = false; se agrega el mismo par para que la fila quede
     * igual que con Artisan.
     */
    public function create(array $atributos = [])
    {
        $tabla = self::tablaDeLaFabrica(get_class($this));
        $ahora = date('Y-m-d H:i:s');

        for ($i = 0; $i < $this->cantidad; $i++) {
            $fila = $atributos;

            if (! array_key_exists('created_at', $fila)) {
                $fila['created_at'] = $ahora;
            }

            if (! array_key_exists('updated_at', $fila)) {
                $fila['updated_at'] = $ahora;
            }

            \Illuminate\Support\Facades\DB::$operaciones[] = [
                'op' => 'insert',
                'tabla' => $tabla,
                'filas' => [$fila],
            ];
        }
    }

    /**
     * Tabla destino de una factoría, deducida del modelo.
     *
     * @param  string  $fabrica  ej. "Database\Factories\CompanyTypeFactory"
     * @return string
     */
    public static function tablaDeLaFabrica($fabrica)
    {
        // Database\Factories\CompanyTypeFactory -> CompanyType
        $modelo = preg_replace('/^.*\\\\/', '', $fabrica);
        $modelo = preg_replace('/Factory$/', '', $modelo);

        $archivo = dirname(__DIR__, 2) . '/app/Models/' . $modelo . '.php';

        if (is_file($archivo)) {
            $fuente = (string) file_get_contents($archivo);

            if (preg_match('/\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $fuente, $m)) {
                return $m[1];
            }
        }

        return self::pluralSnake($modelo);
    }

    /**
     * Plural en snake_case según la convención de Eloquent.
     *
     * No es el pluralizador de Laravel: cubre lo que usan los modelos del
     * proyecto (CompanyType -> company_types, Status -> statuses) y falla hacia la
     * forma más conservadora (+s) en vez de inventar. Si hiciera falta una regla
     * más, el nombre de la tabla se declara en el modelo y esto no se toca.
     */
    public static function pluralSnake($camel)
    {
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $camel));

        if (preg_match('/(s|x|z|ch|sh)$/', $snake)) {
            return $snake . 'es';
        }

        if (preg_match('/[^aeiou]y$/', $snake)) {
            return substr($snake, 0, -1) . 'ies';
        }

        return $snake . 's';
    }
}
