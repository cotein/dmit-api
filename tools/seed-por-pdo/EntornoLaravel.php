<?php

/**
 * Stubs mínimos de Laravel para poder EJECUTAR los archivos reales del proyecto
 * (seeders y el comando de importación) sin levantar el framework.
 *
 * POR QUÉ EXISTE ESTE ARCHIVO:
 * `php artisan` no puede correr en esta máquina. La aplicación pide PHP ^8.1 y el
 * único PHP que tiene pdo_mysql es el 7.4 (el 8.4 arranca artisan pero no tiene
 * driver MySQL). La alternativa sería leer los seeders con expresiones regulares y
 * copiar sus datos a un script nuevo — eso convierte al script en una segunda
 * fuente de verdad que queda vieja en cuanto alguien edita un seeder.
 *
 * En vez de eso se declaran acá las pocas clases de Laravel que los archivos usan
 * (`Illuminate\Database\Seeder`, `Illuminate\Support\Facades\DB`,
 * `Illuminate\Console\Command` y las dos factorías que llama DatabaseSeeder) y se
 * hace `require` de los archivos REALES. Los seeders y el comando se ejecutan de
 * verdad; lo único simulado es el destino de la escritura, que se registra y
 * después se replica por PDO.
 *
 * NADA de vendor/autoload.php se carga a propósito: si se cargara, estas clases
 * chocarían con las de verdad y el stub dejaría de usarse.
 */

namespace Illuminate\Database {

    /**
     * Reemplaza a Illuminate\Database\Seeder.
     *
     * `call()` es lo único que usan los seeders del proyecto: no ejecuta nada,
     * anota qué seeder se pidió. Quien orquesta los ejecuta después, en el mismo
     * orden en que DatabaseSeeder los pide.
     */
    class Seeder
    {
        /** @var array<int, string> seeders pedidos con $this->call(), en orden */
        public static $llamadas = [];

        public function call($clase)
        {
            self::$llamadas[] = $clase;
        }

        public function command($comando, $argumentos = [])
        {
            // Los seeders del proyecto no lo usan; si alguno lo empieza a usar,
            // esto no lo va a ejecutar en silencio.
            throw new \RuntimeException('Seeder::command() no está soportado por el runner PDO.');
        }

        public function setContainer($container)
        {
        }
    }
}

namespace Illuminate\Database\Console\Seeds {

    /**
     * Varias seeders importan este trait sin usarlo. La clase existe para que un
     * `use WithoutModelEvents;` futuro dentro de una clase no rompa el require.
     */
    trait WithoutModelEvents
    {
    }
}

namespace Illuminate\Support\Facades {

    /**
     * Reemplaza a la fachada DB, con DOS modos excluyentes:
     *
     *  - GRABADOR (por defecto): `table()` devuelve un receptor que anota la
     *    operación en vez de ejecutarla. Es lo que usan los seeders: primero se
     *    captura todo lo que piden y después se replica por PDO en un solo lugar.
     *
     *  - PDO: `table()` devuelve un constructor de consultas que SÍ escribe. Es lo
     *    que permite ejecutar el comando de importación y su escritor reales sin
     *    reimplementar nada.
     *
     * Los modos son excluyentes a propósito: si no lo fueran, un seeder podría
     * escribir directo y además quedar anotado para replicarse, y las filas
     * entrarían dos veces.
     */
    class DB
    {
        /**
         * Operaciones registradas en modo grabador, en orden de ejecución.
         *
         * @var array<int, array<string, mixed>>
         */
        public static $operaciones = [];

        /** @var \PDO|null null = modo grabador */
        public static $pdo = null;

        public static function usarPdo(\PDO $pdo)
        {
            self::$pdo = $pdo;
        }

        public static function usarGrabador()
        {
            self::$pdo = null;
        }

        public static function table($tabla)
        {
            if (self::$pdo !== null) {
                return new \Tools\SeedPorPdo\ConstructorConsulta(self::$pdo, $tabla);
            }

            return new \Tools\SeedPorPdo\ReceptorTabla($tabla);
        }

        public static function getSchemaBuilder()
        {
            return new \Tools\SeedPorPdo\ConstructorEsquema(self::$pdo);
        }

        public static function reset()
        {
            self::$operaciones = [];
        }
    }
}

namespace Illuminate\Console {

    /**
     * Reemplaza a Illuminate\Console\Command.
     *
     * Alcanza con lo que usa ImportAfipDatosBase::handle(): leer el argumento y
     * las opciones, y escribir por pantalla. Los valores de entrada los carga el
     * runner antes de llamar a handle(), que es exactamente lo que hace Artisan al
     * parsear la línea de comandos.
     *
     * NO se ejecuta el archivo de ImportCotCatalogs: usa expresiones `match`, que
     * PHP 7.4 no puede ni parsear. Esa parte del paso de COT se replica aparte.
     */
    class Command
    {
        const SUCCESS = 0;
        const FAILURE = 1;

        /** @var array<string, mixed> */
        public $valoresArgumentos = [];

        /** @var array<string, mixed> */
        public $valoresOpciones = [];

        /** @var array<int, array<int, string>> líneas escritas, para el informe */
        public $salida = [];

        /** @var array<int, array{encabezados: array<int, string>, filas: array<int, array<int, mixed>>}> */
        public $tablas = [];

        public function argument($nombre = null)
        {
            return $nombre === null ? $this->valoresArgumentos : (isset($this->valoresArgumentos[$nombre]) ? $this->valoresArgumentos[$nombre] : null);
        }

        public function option($nombre = null)
        {
            if ($nombre === null) {
                return $this->valoresOpciones;
            }

            return isset($this->valoresOpciones[$nombre]) ? $this->valoresOpciones[$nombre] : null;
        }

        public function info($texto)
        {
            $this->salida[] = ['info', $texto];
        }

        public function warn($texto)
        {
            $this->salida[] = ['warn', $texto];
        }

        public function error($texto)
        {
            $this->salida[] = ['error', $texto];
        }

        public function line($texto, $estilo = null)
        {
            $this->salida[] = ['line', $texto];
        }

        public function table($encabezados, $filas)
        {
            // Se guarda además de la línea plana: el informe por catálogo (cuántas
            // filas nuevas, cuántas actualizadas) es la prueba de que el importador
            // escribió lo que dice, y con el texto aplanado no se puede mostrar.
            $this->tablas[] = ['encabezados' => $encabezados, 'filas' => $filas];
            $this->salida[] = ['table', implode(' | ', $encabezados)];
        }
    }
}

namespace Database\Factories {

    use Tools\SeedPorPdo\FabricaStub;

    /**
     * Stubs de las dos factorías que llama DatabaseSeeder.
     *
     * Se declaran acá y NO se ejecutan los archivos reales de database/factories:
     * esos extienden Illuminate\Database\Eloquent\Factories\Factory y arrastran
     * Eloquent entero. Lo que aportan es sólo el nombre del modelo (para saber a
     * qué tabla escribir) y los valores por defecto, que DatabaseSeeder pisa con
     * valores literales.
     */
    class CompanyTypeFactory extends FabricaStub
    {
    }

    class StatusFactory extends FabricaStub
    {
    }
}

namespace {

    // El seeder VouchersTableSeeder llama \DB::table(...) con barra inicial, que
    // resuelve al nombre global; aliasarlo hace que sea la MISMA clase que la
    // fachada, así el estado registrado no se parte en dos.
    if (! class_exists('DB', false)) {
        class_alias('Illuminate\\Support\\Facades\\DB', 'DB');
    }

    /**
     * Reemplaza al helper env() de Laravel.
     *
     * La usa ImportAfipDatosBase::directorio() para resolver ARCA_DATOS_BASE_CSV.
     * Se lee del .env con el mismo lector que la conexión, para que el comando real
     * vea exactamente la misma configuración que vería bajo Artisan.
     */
    function env($clave, $porDefecto = null)
    {
        static $valores = null;

        if ($valores === null) {
            try {
                $valores = \Tools\SeedPorPdo\Conexion::leerEnv(dirname(__DIR__, 2) . '/.env');
            } catch (\Throwable $e) {
                $valores = [];
            }
        }

        return isset($valores[$clave]) ? $valores[$clave] : $porDefecto;
    }

    /**
     * Reemplaza al helper now() de Laravel.
     *
     * Lo usa EscritorDatosBaseLaravel para los timestamps de cada alta y de cada
     * actualización. Se devuelve un DateTimeInterface —igual que Laravel, que
     * devuelve un Carbon— y el constructor de consultas lo formatea al enlazarlo.
     */
    function now()
    {
        return new \DateTimeImmutable();
    }
}
