<?php

namespace App\Src\Afip\DatosBase;

/**
 * Aplica a la base las filas que ya leyó LectorDatosBase.
 *
 * No sabe leer archivos ni sabe de Laravel: recibe el lector, el escritor y la
 * ruta, y devuelve un informe. Esa separación es lo que permite verificar el
 * comando sin `php artisan` (ver EscritorDatosBase).
 *
 * IDEMPOTENCIA: la decisión de qué hacer con cada fila sale de comparar contra la
 * instantánea del destino, no de contar filas. Correr el importador dos veces
 * deja la segunda corrida en "sin cambios" para todas las filas, sin importar
 * cuántas veces se corrió antes.
 */
final class ImportadorDatosBase
{
    /**
     * Importa un catálogo.
     *
     * @param  array<string, mixed>  $catalogo  entrada de ConfiguracionDatosBase::catalogos()
     * @param  string  $ruta  archivo a leer
     * @param  EscritorDatosBase  $escritor
     * @param  bool  $dryRun  si es true no se escribe nada: sólo se informa
     * @return array<string, mixed>  informe
     */
    public static function importar(array $catalogo, $ruta, EscritorDatosBase $escritor, $dryRun = false)
    {
        $destino = $catalogo['destino'];
        $lectura = LectorDatosBase::leer($catalogo, $ruta);

        $informe = [
            'catalogo' => '',
            'ruta' => $ruta,
            'tabla' => $destino['tabla'],
            'modo' => $destino['modo'],
            'preambulo' => $lectura['preambulo'],
            'cabecera' => $lectura['cabecera'],
            'lineas' => $lectura['lineas'],
            'bom' => $lectura['bom'],
            'leidas' => $lectura['leidas'],
            'nuevas' => 0,
            'actualizadas' => 0,
            'sin_cambios' => 0,
            'respetadas' => 0,
            'diferencias' => [],
            'salteadas' => $lectura['salteadas'],
            'sobrantes' => [],
            'colisiones' => [],
            'problemas' => $lectura['problemas'],
            'error' => null,
        ];

        if ($lectura['problemas'] !== []) {
            // Validación de columnas fallada: se avisa y NO se escribe nada.
            $informe['error'] = 'el archivo no coincide con la configuración; no se importó nada';

            return $informe;
        }

        if (! $escritor->tablaExiste($destino['tabla'])) {
            $informe['error'] = "no existe la tabla {$destino['tabla']}";

            return $informe;
        }

        $actuales = $escritor->instantanea($destino);
        $informe['colisiones'] = self::colisiones($actuales);

        $vistas = [];

        foreach ($lectura['filas'] as $fila) {
            $clave = $fila[$catalogo['destino']['clave']];
            $claveNormalizada = LectorDatosBase::normalizar($clave);

            // El ejecutor de --dry-run es el único que decide no escribir.
            $vistas[$claveNormalizada] = $clave;

            if (isset($actuales[$claveNormalizada])) {
                $actual = $actuales[$claveNormalizada];

                if (self::iguales($catalogo, $fila, $actual)) {
                    $informe['sin_cambios']++;

                    continue;
                }

                if ($destino['modo'] === ConfiguracionDatosBase::MODO_ALTA) {
                    // MODO_ALTA nunca pisa la descripción cargada: el nombre que ya
                    // está en la tabla manda (ver el docblock de la constante). La
                    // diferencia NO se descarta en silencio: se cuenta y se lista,
                    // que es como se ve que ARCA escribe "Catamara".
                    $informe['respetadas']++;
                    $informe['diferencias'][] = $actual['clave'] . ': ARCA "' . $fila[$destino['descripcion']] . '" vs local "' . $actual['descripcion'] . '"';

                    continue;
                }

                $informe['actualizadas']++;

                if (! $dryRun) {
                    $escritor->actualizar($destino, $actual['clave'], self::columnas($catalogo, $fila, false));
                }

                continue;
            }

            $informe['nuevas']++;

            if (! $dryRun) {
                $escritor->insertar($destino, self::columnas($catalogo, $fila, true));
            }
        }

        // Filas que están en la tabla y el archivo no trae. No se borra nada: se
        // informa. Es el mismo criterio que SincronizadorPadron100 con `sobrantes`.
        foreach ($actuales as $claveNormalizada => $actual) {
            if (! isset($vistas[$claveNormalizada])) {
                $informe['sobrantes'][] = $actual['clave'];
            }
        }

        return $informe;
    }

    /**
     * Fila final a escribir, ya con los valores por defecto aplicados si es alta.
     *
     * Los valores por defecto van SÓLO en el alta: si se aplicaran también en la
     * actualización, el importador pisaría columnas que no son suyas con un valor
     * constante (por ejemplo `moneys.symbol`, que hoy está vacío pero que otro
     * proceso podría llenar). El importador sólo es dueño de las columnas que el
     * archivo trae.
     *
     * @param  array<string, mixed>  $catalogo
     * @param  array<string, string>  $fila
     * @return array<string, string>
     */
    private static function columnas(array $catalogo, array $fila, $esAlta)
    {
        if (! $esAlta) {
            return $fila;
        }

        foreach ($catalogo['por_defecto'] as $campo => $valor) {
            // `short` viene como mapa código => sigla, porque ARCA no publica
            // siglas y hay que sacarlas del seeder que ya está en el proyecto.
            if (is_array($valor)) {
                $clave = $fila[$catalogo['destino']['clave']];
                $fila[$campo] = isset($valor[$clave]) ? $valor[$clave] : '';

                continue;
            }

            if (! isset($fila[$campo])) {
                $fila[$campo] = $valor;
            }
        }

        return $fila;
    }

    /**
     * ¿La fila del archivo dice lo mismo que lo que ya está guardado?
     *
     * @param  array<string, mixed>  $catalogo
     * @param  array<string, string>  $fila
     * @param  array{clave: string, descripcion: string, valores: array<string, string>}  $actual
     * @return bool
     */
    private static function iguales(array $catalogo, array $fila, array $actual)
    {
        $campos = self::campos($catalogo);
        $descripcion = $catalogo['destino']['descripcion'];

        if (isset($fila[$descripcion]) && $fila[$descripcion] !== $actual['descripcion']) {
            return false;
        }

        return self::huella($campos, $fila) === self::huella($campos, $actual['valores']);
    }

    /**
     * Columnas que el archivo aporta, ordenadas por nombre.
     *
     * El orden se fija acá y no depende del orden del array de la configuración:
     * si no, dos conjuntos iguales podrían verse distintos sólo por el orden.
     *
     * @param  array<string, mixed>  $catalogo
     * @return array<int, string>
     */
    private static function campos(array $catalogo)
    {
        $campos = array_values($catalogo['columnas']);
        sort($campos);

        return $campos;
    }

    /**
     * Huella de una fila usando SÓLO las columnas del archivo (más lo que aporten
     * los valores por defecto). La usan los dos lados de la comparación, la fila
     * del archivo y la fila guardada: es lo que hace que la idempotencia no
     * dependa de que cada escritor se acuerde de incluir los mismos campos.
     *
     * @param  array<int, string>  $campos
     * @param  array<string, mixed>  $valores
     * @return string
     */
    private static function huella(array $campos, array $valores)
    {
        $par = [];

        foreach ($campos as $campo) {
            $par[] = $campo . '=' . (isset($valores[$campo]) ? (string) $valores[$campo] : '');
        }

        return implode("\x1f", $par);
    }

    /**
     * Claves guardadas que normalizan al mismo valor (ej. "PES" y "PES ").
     *
     * No se resuelve solo: se informa, porque son filas duplicadas en la tabla y
     * quien mira el informe tiene que enterarse.
     *
     * @param  array<string, array{clave: string, descripcion: string, valores: array<string, string>}>  $actuales
     * @return array<int, string>
     */
    private static function colisiones(array $actuales)
    {
        $vistas = [];
        $colisiones = [];

        foreach ($actuales as $clave => $actual) {
            if (isset($vistas[$clave])) {
                $colisiones[] = $vistas[$clave] . ' / ' . $actual['clave'];

                continue;
            }

            $vistas[$clave] = $actual['clave'];
        }

        return $colisiones;
    }

    /**
     * Totales de una lista de informes, en el formato que ya usa el proyecto.
     *
     * @param  array<int, array<string, mixed>>  $informes
     * @return array<string, int>
     */
    public static function resumir(array $informes)
    {
        $resumen = [
            'catalogos' => count($informes),
            'leidas' => 0,
            'nuevas' => 0,
            'actualizadas' => 0,
            'sin_cambios' => 0,
            'respetadas' => 0,
            'salteadas' => 0,
            'sobrantes' => 0,
            'con_error' => 0,
        ];

        foreach ($informes as $informe) {
            $resumen['leidas'] += $informe['leidas'];
            $resumen['nuevas'] += $informe['nuevas'];
            $resumen['actualizadas'] += $informe['actualizadas'];
            $resumen['sin_cambios'] += $informe['sin_cambios'];
            $resumen['respetadas'] += $informe['respetadas'];
            $resumen['salteadas'] += count($informe['salteadas']);
            $resumen['sobrantes'] += count($informe['sobrantes']);

            if ($informe['error'] !== null) {
                $resumen['con_error']++;
            }
        }

        return $resumen;
    }
}
