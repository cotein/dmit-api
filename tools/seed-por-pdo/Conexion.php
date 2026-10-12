<?php

namespace Tools\SeedPorPdo;

/**
 * Conexión PDO a la base de la aplicación, leída del .env a mano.
 *
 * POR QUÉ NO SE USA Laravel NI parse_ini_file:
 *  - Laravel no arranca: la aplicación pide PHP ^8.1 y el único PHP de la máquina
 *    con pdo_mysql es el 7.4 (el 8.4 arranca artisan pero no tiene driver MySQL).
 *    Por eso este runner es PDO puro y no carga el autoload de vendor.
 *  - parse_ini_file() sobre este .env devuelve false: el archivo tiene valores con
 *    caracteres que el parser de INI no acepta (contraseñas y claves de API con
 *    símbolos), así que se lee línea por línea. Es el único lector que funciona
 *    con el archivo real.
 *
 * Las variables de entorno de Laravel (${VAR}) SÍ se resuelven, porque el .env del
 * proyecto las usa para no repetir la misma credencial en varias claves.
 */
final class Conexion
{
    /**
     * Abre la conexión a partir del .env del proyecto.
     *
     * @param  string  $raiz  directorio que contiene el .env
     * @return \PDO
     */
    public static function abrir($raiz)
    {
        $valores = self::leerEnv($raiz . '/.env');

        foreach (['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'] as $clave) {
            if (! isset($valores[$clave])) {
                throw new \RuntimeException("Falta {$clave} en el .env: no se puede conectar.");
            }
        }

        $puerto = isset($valores['DB_PORT']) && $valores['DB_PORT'] !== '' ? $valores['DB_PORT'] : '3306';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $valores['DB_HOST'],
            $puerto,
            $valores['DB_DATABASE']
        );

        $pdo = new \PDO($dsn, $valores['DB_USERNAME'], $valores['DB_PASSWORD'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            // Emulación desactivada para que los tipos viajen como tipos y no
            // como texto: importa al comparar instantáneas (id 1 vs '1').
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }

    /**
     * Lee el .env línea por línea y devuelve las claves ya resueltas.
     *
     * @param  string  $ruta
     * @return array<string, string>
     */
    public static function leerEnv($ruta)
    {
        if (! is_file($ruta)) {
            throw new \RuntimeException("No existe {$ruta}.");
        }

        $crudo = [];
        $lineas = preg_split("/\r\n|\r|\n/", (string) file_get_contents($ruta));

        foreach ($lineas as $linea) {
            $linea = trim($linea);

            // Comentarios y líneas vacías: el .env real los tiene por todos lados.
            if ($linea === '' || $linea[0] === '#') {
                continue;
            }

            $pos = strpos($linea, '=');

            if ($pos === false) {
                continue;
            }

            $clave = trim(substr($linea, 0, $pos));
            $valor = trim(substr($linea, $pos + 1));

            // Comillas envolventes: se sacan sólo si envuelven TODO el valor, para
            // no comerse una comilla que forma parte de una contraseña.
            if (strlen($valor) >= 2) {
                $primero = $valor[0];
                $ultimo = substr($valor, -1);

                if (($primero === '"' && $ultimo === '"') || ($primero === "'" && $ultimo === "'")) {
                    $valor = substr($valor, 1, -1);
                }
            }

            $crudo[$clave] = $valor;
        }

        return self::resolver($crudo);
    }

    /**
     * Reemplaza las referencias ${VAR} por el valor de esa misma clave.
     *
     * Se resuelve en cascada (una variable puede referenciar a otra) con un tope de
     * pasadas: sin el tope, una referencia circular colgaría el proceso en vez de
     * fallar. Lo que quede sin resolver se deja tal cual, para que se vea en vez de
     * convertirse en una cadena vacía silenciosa.
     *
     * @param  array<string, string>  $valores
     * @return array<string, string>
     */
    public static function resolver(array $valores)
    {
        for ($pasada = 0; $pasada < 10; $pasada++) {
            $cambio = false;

            foreach ($valores as $clave => $valor) {
                if (strpos($valor, '${') === false) {
                    continue;
                }

                $nuevo = preg_replace_callback('/\$\{([A-Za-z0-9_]+)\}/', function ($m) use ($valores) {
                    return isset($valores[$m[1]]) ? $valores[$m[1]] : $m[0];
                }, $valor);

                if ($nuevo !== $valor) {
                    $valores[$clave] = $nuevo;
                    $cambio = true;
                }
            }

            if (! $cambio) {
                break;
            }
        }

        return $valores;
    }
}
