<?php

namespace App\Src\Afip\DatosBase;

/**
 * Lo que el importador necesita de la base, sin saber si atrás hay Laravel o PDO.
 *
 * POR QUÉ EXISTE LA INTERFAZ: `php artisan` no corre en la máquina de desarrollo
 * (la aplicación pide PHP ^8.1 y el único PHP con pdo_mysql es el 7.4), así que la
 * única forma de probar esto de verdad es un script PDO aparte. Con la interfaz,
 * ese script usa el MISMO importador y el MISMO lector que el comando, en vez de
 * reimplementar la lógica y verificar una copia.
 */
interface EscritorDatosBase
{
    /**
     * ¿Existe la tabla destino? Si no, el catálogo no se puede importar.
     *
     * @param  string  $tabla
     * @return bool
     */
    public function tablaExiste($tabla);

    /**
     * Estado actual del destino, indexado por la clave natural NORMALIZADA.
     *
     * Devuelve los valores CRUDOS guardados, no una huella: la huella la calcula
     * el importador con la misma función para la fila del archivo y para la fila
     * guardada. Si cada uno calculara la suya, cualquier diferencia en la lista de
     * campos haría que todas las filas se vieran "actualizadas" en cada corrida y
     * el importador dejaría de ser idempotente.
     *
     * Indexar por la clave normalizada es lo que hace tolerante al importador con
     * las variantes que dejó el seeder hecho a mano: `moneys.code` guarda "PES "
     * con un espacio al final y el archivo de ARCA trae "PES". Comparando las dos
     * normalizadas, la corrida actualiza esa fila en vez de crear un duplicado.
     *
     * @param  array<string, mixed>  $destino
     * @return array<string, array{clave: string, descripcion: string, valores: array<string, string>}>
     */
    public function instantanea(array $destino);

    /**
     * Inserta una fila.
     *
     * @param  array<string, mixed>  $destino
     * @param  array<string, string>  $columnas
     */
    public function insertar(array $destino, array $columnas);

    /**
     * Actualiza la fila identificada por $claveReal (el valor tal cual está
     * guardado, no el normalizado).
     *
     * @param  array<string, mixed>  $destino
     * @param  string  $claveReal
     * @param  array<string, string>  $columnas
     */
    public function actualizar(array $destino, $claveReal, array $columnas);
}
