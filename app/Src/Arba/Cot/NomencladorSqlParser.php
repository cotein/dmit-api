<?php

namespace App\Src\Arba\Cot;

/**
 * Lector de las tuplas de un INSERT multi-row del script de ARBA.
 *
 * Existe como clase aparte porque es la parte frágil de la importación y porque
 * el archivo lo genera ARBA, no nosotros: hay que tolerar su formato tal cual
 * viene, con los INSERT partidos cada 500 filas y las descripciones llenas de
 * comas, paréntesis y puntos.
 *
 * Las dos reglas del formato que importan:
 *   - el separador de campos dentro de una tupla es la coma, pero una coma
 *     dentro de una cadena NO separa nada;
 *   - el escape de una comilla simple dentro de una cadena es '' (dos comillas
 *     simples), no la barra invertida.
 *
 * Un parser tonto con expresiones regulares se rompe con la primera descripción
 * que traiga un paréntesis (por ejemplo "UNIDAD(ES)"), así que esto es un
 * scanner carácter por carácter.
 */
final class NomencladorSqlParser
{
    /**
     * Devuelve todas las tuplas del script, como listas de campos crudos.
     *
     * @return array<int, string[]>
     */
    public static function tuplas(string $sql): array
    {
        $tuplas = [];
        $campos = null;
        $campo = '';
        $enCadena = false;
        $largo = strlen($sql);

        for ($i = 0; $i < $largo; $i++) {
            $caracter = $sql[$i];

            if ($enCadena) {
                if ($caracter === "'") {
                    // '' es una comilla escapada, no el cierre de la cadena.
                    if ($i + 1 < $largo && $sql[$i + 1] === "'") {
                        $campo .= "'";
                        $i++;
                        continue;
                    }

                    $enCadena = false;
                    continue;
                }

                $campo .= $caracter;
                continue;
            }

            if ($caracter === "'") {
                $enCadena = true;
                continue;
            }

            if ($caracter === '(') {
                $campos = [];
                $campo = '';
                continue;
            }

            if ($caracter === ',') {
                if ($campos !== null) {
                    $campos[] = trim($campo);
                    $campo = '';
                }
                continue;
            }

            if ($caracter === ')') {
                if ($campos !== null) {
                    $campos[] = trim($campo);
                    $tuplas[] = $campos;
                    $campos = null;
                    $campo = '';
                }
                continue;
            }

            if ($campos !== null) {
                $campo .= $caracter;
            }
        }

        return $tuplas;
    }

    /**
     * Filas del nomenclador ya normalizadas.
     *
     * Se descartan las tuplas que no tienen la forma esperada: el script trae
     * otras sentencias (el CREATE TABLE, los comentarios) y sus paréntesis no
     * son filas.
     *
     * @return array<int, array{codigo: string, codigo_padre: ?string, nivel: int, tipo: string, descripcion: string, unidad_medida: ?string}>
     */
    public static function filas(string $sql): array
    {
        $filas = [];
        $tipos = ['CAPITULO', 'RUBRO', 'VARIEDAD'];

        foreach (self::tuplas($sql) as $tupla) {
            if (count($tupla) !== 6) {
                continue;
            }

            [$codigo, $padre, $nivel, $tipo, $descripcion, $unidad] = $tupla;

            if (! in_array($tipo, $tipos, true) || ! ctype_digit($nivel)) {
                continue;
            }

            if ($codigo === '' || $descripcion === '') {
                continue;
            }

            $filas[] = [
                'codigo' => $codigo,
                'codigo_padre' => $padre === 'NULL' ? null : $padre,
                'nivel' => (int) $nivel,
                'tipo' => $tipo,
                'descripcion' => $descripcion,
                'unidad_medida' => $unidad === 'NULL' ? null : $unidad,
            ];
        }

        return $filas;
    }

    /**
     * Ordena las filas para que ningún hijo se inserte antes que su padre.
     *
     * La tabla tiene una FK auto-referencial y el script de ARBA viene
     * entrelazado: ordenar por largo de código alcanza porque el padre siempre
     * es un prefijo más corto (2 -> 4 -> 5 -> 6 dígitos).
     *
     * @param  array<int, array{codigo: string}>  $filas
     * @return array<int, array<string, mixed>>
     */
    public static function ordenarPadresPrimero(array $filas): array
    {
        usort($filas, function (array $a, array $b) {
            return [strlen($a['codigo']), $a['codigo']] <=> [strlen($b['codigo']), $b['codigo']];
        });

        return $filas;
    }
}
