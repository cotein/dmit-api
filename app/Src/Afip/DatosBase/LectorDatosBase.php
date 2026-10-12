<?php

namespace App\Src\Afip\DatosBase;

/**
 * Lee un archivo de catálogo de ARCA y devuelve las filas ya mapeadas, sin
 * tocar la base.
 *
 * POR QUÉ ESTÁ SEPARADO DEL IMPORTADOR: para poder probar la lectura —que es
 * donde está el riesgo real, porque los offsets del preámbulo son distintos en
 * cada archivo y una fila corrida mete basura en la base— sin base de datos de
 * por medio. El importador recibe filas ya limpias y sólo decide qué escribir.
 *
 * Sin dependencias: fgetcsv() de PHP, como pidió el dueño. El proyecto no tiene
 * ninguna librería de Excel y no se agrega ninguna; los .xls se convierten a CSV
 * con LibreOffice antes de importar (ver el docblock del comando).
 */
final class LectorDatosBase
{
    /**
     * Lee el catálogo y lo valida contra la configuración.
     *
     * No lanza excepciones: devuelve un resultado con `ok` en false y la lista de
     * `problemas` cuando el archivo no coincide con lo configurado. El importador
     * se encarga de no escribir nada en ese caso.
     *
     * @param  array<string, mixed>  $catalogo  una entrada de ConfiguracionDatosBase::catalogos()
     * @param  string  $ruta  archivo a leer
     * @return array<string, mixed>
     */
    public static function leer(array $catalogo, $ruta)
    {
        $resultado = [
            'ok' => false,
            'ruta' => $ruta,
            'lineas' => 0,
            'preambulo' => (int) $catalogo['preambulo'],
            'cabecera' => [],
            'problemas' => [],
            'filas' => [],
            'salteadas' => [],
            'leidas' => 0,
            'bom' => false,
        ];

        if (! is_file($ruta)) {
            $resultado['problemas'][] = "no existe el archivo {$ruta}";

            return $resultado;
        }

        $lineas = self::lineas($ruta, $resultado);

        if ($lineas === []) {
            $resultado['problemas'][] = 'el archivo está vacío';

            return $resultado;
        }

        // El preámbulo son las filas de título y de relleno que ARCA pone antes
        // del encabezado real. Se saltean de a una cantidad declarada por archivo.
        $resto = array_slice($lineas, $resultado['preambulo']);

        if ($resto === []) {
            $resultado['problemas'][] = "el archivo tiene {$resultado['lineas']} filas, menos que el preámbulo declarado ({$resultado['preambulo']})";

            return $resultado;
        }

        $separador = isset($catalogo['separador']) ? $catalogo['separador'] : ',';
        $cabecera = self::celdas(array_shift($resto), $separador);
        $resultado['cabecera'] = $cabecera;

        // Validación de columnas: es la defensa contra que ARCA cambie el formato
        // del archivo. Si no coincide, se corta y NO se importa a ciegas.
        $resultado['problemas'] = self::validarCabecera($catalogo, $cabecera);

        if ($resultado['problemas'] !== []) {
            return $resultado;
        }

        $resultado['leidas'] = count($resto);

        foreach ($resto as $i => $linea) {
            $numero = $resultado['preambulo'] + $i + 2;   // 1-based, contando el encabezado
            $fila = self::mapear($catalogo, self::celdas($linea, $separador), $numero, $resultado);

            if ($fila !== null) {
                $resultado['filas'][] = $fila;
            }
        }

        $resultado['ok'] = true;

        return $resultado;
    }

    /**
     * Líneas con contenido del archivo, sin el fin de línea y sin BOM.
     *
     * Se lee en modo binario y se corta por \r\n|\r|\n en vez de usar file():
     * el nomenclador de actividades viene con CRLF y deja un \r colgado en la
     * última celda de cada fila, que después ensucia la descripción.
     *
     * @param  array<string, mixed>  $resultado  se marca ahí si el archivo traía BOM
     * @return array<int, string>
     */
    private static function lineas($ruta, array &$resultado)
    {
        $contenido = (string) file_get_contents($ruta);

        // El BOM UTF-8 (EF BB BF) hay que sacarlo o el primer código queda con
        // tres caracteres invisibles adelante. Se comprueba de verdad en vez de
        // darlo por hecho: el archivo de actividades que entregó el dueño NO lo
        // trae (arranca con "COD_ACTIVIDAD_F883"), pero el de ARCA suele traerlo.
        if (substr($contenido, 0, 3) === "\xEF\xBB\xBF") {
            $contenido = substr($contenido, 3);
            $resultado['bom'] = true;
        }

        $lineas = [];

        foreach (preg_split('/\r\n|\r|\n/', $contenido) as $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $lineas[] = $linea;
        }

        $resultado['lineas'] = count($lineas);

        return $lineas;
    }

    /**
     * Corta una línea en celdas. Usa fgetcsv sobre un stream en memoria para no
     * escribir un parser propio: las descripciones de ARCA traen comas y
     * comillas adentro (ej. `034,"COMPROBANTES A DEL ANEXO I, APARTADO A, ..."`)
     * y sólo un lector CSV de verdad las resuelve.
     *
     * @return array<int, string>
     */
    private static function celdas($linea, $separador)
    {
        $fh = fopen('php://memory', 'r+');
        fwrite($fh, (string) $linea);
        rewind($fh);

        // escape vacío: desactiva el tratamiento de la barra invertida, que en
        // PHP 8.4 está deprecado y que estas tablas no usan.
        $celdas = fgetcsv($fh, 0, $separador, '"', '');
        fclose($fh);

        if ($celdas === false || $celdas === null) {
            return [];
        }

        $salida = [];

        foreach ($celdas as $celda) {
            $salida[] = trim((string) $celda);
        }

        return $salida;
    }

    /**
     * Compara el encabezado real contra el declarado y devuelve los problemas.
     *
     * @param  array<string, mixed>  $catalogo
     * @param  array<int, string>  $cabecera
     * @return array<int, string>
     */
    private static function validarCabecera(array $catalogo, array $cabecera)
    {
        $problemas = [];
        $esperada = $catalogo['cabecera'];

        foreach ($esperada as $i => $nombre) {
            if ($nombre === null) {
                // Posición sin nombre en el archivo de ARCA (INCOTERMS): no se valida.
                continue;
            }

            $actual = isset($cabecera[$i]) ? self::normalizar($cabecera[$i]) : '';

            if ($actual !== self::normalizar($nombre)) {
                $problemas[] = sprintf(
                    'la columna %d debería ser "%s" y el archivo dice "%s"',
                    $i + 1,
                    $nombre,
                    isset($cabecera[$i]) ? $cabecera[$i] : '(falta)'
                );
            }
        }

        // Una columna que la configuración no declara es exactamente el caso que
        // hay que frenar: si ARCA agrega una, los datos podrían venir en otro orden.
        for ($i = count($esperada); $i < count($cabecera); $i++) {
            if (self::normalizar($cabecera[$i]) !== '') {
                $problemas[] = sprintf('el archivo trae una columna %d ("%s") que la configuración no declara', $i + 1, $cabecera[$i]);
            }
        }

        return $problemas;
    }

    /**
     * Mapea una fila a las columnas del destino. Devuelve null y anota el motivo
     * cuando la fila no sirve.
     *
     * @param  array<string, mixed>  $catalogo
     * @param  array<int, string>  $celdas
     * @param  array<string, mixed>  $resultado
     * @return array<string, string>|null
     */
    private static function mapear(array $catalogo, array $celdas, $numero, array &$resultado)
    {
        // El código está siempre en la primera columna en los 13 archivos, y la
        // validación del encabezado fija el nombre de esa posición para cada
        // catálogo: si ARCA reordenara las columnas, el importador no llegaría acá.
        $codigo = isset($celdas[0]) ? $celdas[0] : '';

        if ($codigo === '') {
            // La línea en blanco que separa la tabla de su nota al pie
            // (TABLACOMPROBANTES la tiene como "," suelto).
            $resultado['salteadas'][] = "fila {$numero}: vacía";

            return null;
        }

        if (isset($catalogo['codigo_sin_artefactos']) && $catalogo['codigo_sin_artefactos']) {
            // "´002" en vez de "002": el acento agudo se pegó al convertir el Excel.
            $codigo = (string) preg_replace('/^[^0-9A-Za-z]+/', '', $codigo);
        }

        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            // MONEDAS termina con una fila que sólo trae relleno invisible
            // (espacio duro + espacio): trim() no saca el espacio duro, así que no
            // la agarra el control de arriba.
            $resultado['salteadas'][] = "fila {$numero}: sin código (la primera celda sólo traía relleno)";

            return null;
        }

        if (! preg_match($catalogo['patron'], $codigo)) {
            // Acá caen la nota al pie de TABLACOMPROBANTES
            // ("(*) Solo emitidos con Controladores Fiscales.") y cualquier fila
            // cuyo código no tenga la forma que ARCA documenta.
            $resultado['salteadas'][] = "fila {$numero}: el código \"{$codigo}\" no cumple {$catalogo['patron']}";

            return null;
        }

        $fila = [];

        foreach ($catalogo['columnas'] as $posicion => $campo) {
            $valor = isset($celdas[$posicion]) ? $celdas[$posicion] : '';

            if ($posicion === 0) {
                $valor = $codigo;
            }

            $fila[$campo] = $valor;
        }

        // Erratas conocidas de ARCA, declaradas en la configuración y aplicadas
        // por código (ej. "Catamara" -> "Catamarca" en la tabla de provincias).
        if (isset($catalogo['normalizar'][$codigo])) {
            foreach ($catalogo['normalizar'][$codigo] as $campo => $valor) {
                $fila[$campo] = $valor;
            }
        }

        return $fila;
    }

    /**
     * Normaliza un nombre de columna para compararlo: sin espacios de sobra, sin
     * acentos y en minúsculas.
     *
     * Los acentos se sacan porque el encabezado real varía entre archivos
     * ("Codigo" en UNIDADES DE MEDIDA y "Código" en el resto) y porque una
     * reconversión del .xls puede cambiar la normalización Unicode. Lo que NO se
     * tolera es un nombre distinto, que es el cambio que hay que detectar.
     */
    public static function normalizar($texto)
    {
        $texto = self::sinAcentos(mb_strtolower(trim((string) $texto), 'UTF-8'));

        $texto = (string) preg_replace('/[^a-z0-9]+/', ' ', $texto);

        return trim($texto);
    }

    /**
     * Saca los acentos con una tabla propia en vez de iconv o intl: el proyecto
     * no declara ninguna de las dos y esto se usa para comparar encabezados.
     */
    private static function sinAcentos($texto)
    {
        static $tabla = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ã' => 'a', 'ñ' => 'n', 'ç' => 'c',
        ];

        return strtr($texto, $tabla);
    }
}
