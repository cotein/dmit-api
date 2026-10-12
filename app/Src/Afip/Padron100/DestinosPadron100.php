<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * A qué tabla del sistema va cada colección de ARCA.
 *
 * Esto es lo que pidió el dueño: usar el servicio para lo que da, y lo que no está lo
 * agrega él en tablas. Por eso el mapa es explícito y está en un solo lugar: agregar un
 * destino nuevo es agregar una entrada acá (y la tabla del otro lado).
 *
 * Hoy sólo hay dos destinos:
 *
 *  - SUPA.TIPO_DOCUMENTO -> afip_documents. Es el objetivo principal: hoy esos 38 tipos
 *    están cargados a mano en AfipDocumentsTableSeeder y pueden quedar viejos respecto
 *    del padrón real de ARCA.
 *
 *  - SUPA.E_PROVINCIA -> states, y en modo CONTRASTE. Las provincias ya están cargadas
 *    (AfipStatesTableSeeder trae las 24 con su afip_code, y el módulo de ARBA les agrega
 *    la letra del COT en cot_code / cot_provincias). No se pisan: sólo se comparan y se
 *    informan las diferencias.
 *
 * El resto de las colecciones se guarda crudo en afip_parametros y nada más.
 *
 * Notación de las columnas: '@id' y '@descripcion' son campos del registro de ARCA; un
 * nombre sin arroba es un atributo de <attributeList> (con la descripción como respaldo
 * si el atributo no viene, como pasa con NOMBRE_PROVINCIA).
 */
final class DestinosPadron100
{
    public const TABLA_DOCUMENTOS = 'afip_documents';
    public const TABLA_PROVINCIAS = 'states';

    /** Se escriben en la tabla destino. */
    public const MODO_ESCRITURA = 'escritura';

    /** Sólo se comparan contra lo que ya hay, sin tocar nada (las provincias). */
    public const MODO_CONTRASTE = 'contraste';

    /**
     * @return array<string, string>|null tabla, modo, clave, codigo, nombre
     */
    public static function destinoDe(string $coleccion): ?array
    {
        $destinos = [
            ColeccionesPadron100::TIPO_DOCUMENTO => [
                'tabla' => self::TABLA_DOCUMENTOS,
                'modo' => self::MODO_ESCRITURA,
                'clave' => 'afip_code',
                'codigo' => '@id',
                'nombre' => '@descripcion',
            ],
            ColeccionesPadron100::PROVINCIA => [
                'tabla' => self::TABLA_PROVINCIAS,
                'modo' => self::MODO_CONTRASTE,
                'clave' => 'afip_code',
                'codigo' => '@id',
                'nombre' => 'NOMBRE_PROVINCIA',
            ],
        ];

        return isset($destinos[$coleccion]) ? $destinos[$coleccion] : null;
    }

    /**
     * Columnas de escritura permitidas por tabla. Es una lista cerrada a propósito: el
     * nombre de tabla y de columna no puede venir de afuera (inyección por nombre).
     *
     * @return array<string, array<string, string>>
     */
    public static function tablasEscribibles(): array
    {
        return [
            self::TABLA_DOCUMENTOS => ['clave' => 'afip_code', 'nombre' => 'name'],
        ];
    }

    /**
     * Valor de la columna pedida para un registro de ARCA.
     *
     * @param  string[]  $destino  Una entrada de destinoDe()
     */
    public static function valor(array $destino, string $campo, ParametroPadron100 $parametro): string
    {
        $origen = $destino[$campo];

        if (strpos($origen, '@') === 0) {
            $nombre = substr($origen, 1);

            if ($nombre === 'id') {
                return $parametro->id();
            }

            if ($nombre === 'descripcion') {
                return (string) $parametro->descripcion();
            }

            return '';
        }

        $atributo = $parametro->atributo($origen);

        if ($atributo !== null && $atributo !== '') {
            return $atributo;
        }

        // Respaldo: si el atributo pedido no vino, la descripción del registro.
        return (string) $parametro->descripcion();
    }
}
