<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

/**
 * Nombres de colección que acepta el WS ws_sr_padron_a100 (Anexo 5.1, manual v2.1).
 *
 * Por qué una clase de constantes y no una tabla: el nombre viaja como texto en el
 * request y ARCA contesta error si no coincide exactamente con el del Anexo, así que
 * conviene escribirlo una sola vez y tenerlo en un solo lugar (además el catálogo es
 * cerrado y chico).
 *
 * OJO con el copy/paste del PDF: en la tabla del Anexo los nombres largos quedan
 * cortados por el ancho de la columna. Por ejemplo el Anexo imprime
 * "SUPA.E_ORGANISMO_INFORMANT" y en el renglón siguiente "E": el nombre real es
 * SUPA.E_ORGANISMO_INFORMANTE. Acá van todos reconstruidos y unidos; copiar el texto
 * cortado del PDF es un error garantizado.
 *
 * Los dos nombres que quedaron marcados con "reconstruido con duda" son los únicos
 * donde la unión de los pedazos no forma una palabra evidente (el resto son
 * concatenaciones obvias: IDENTIFICACI+ON, ADICIONAL_DO+MICILIO, ACTIVIDAD_OCUPACIONA+L).
 * Ninguno de los que usa la sincronización está en ese grupo.
 */
final class ColeccionesPadron100
{
    public const PROVINCIA = 'SUPA.E_PROVINCIA';
    public const LOCALIDAD = 'PUC_PARAM.T_LOCALIDAD';
    public const CALLE = 'PUC_PARAM.T_CALLE';
    public const TIPO_DOCUMENTO = 'SUPA.TIPO_DOCUMENTO';
    public const TIPO_CLAVE_IDENTIFICACION = 'SUPA.TIPO_CLAVE_IDENTIFICACION';
    public const TIPO_DOMICILIO = 'SUPA.TIPO_DOMICILIO';
    public const TIPO_DATO_ADICIONAL_DOMICILIO = 'SUPA.TIPO_DATO_ADICIONAL_DOMICILIO';
    public const TIPO_EMAIL = 'SUPA.TIPO_EMAIL';
    public const SEXO = 'E_SEXO';
    public const TIPO_RESIDENCIA = 'SUPA.TIPO_RESIDENCIA';
    public const REGIMEN = 'SUPA.E_REGIMEN';
    public const TIPO_REGIMEN = 'SUPA.TIPO_REGIMEN';
    public const CARACTERIZACION = 'SUPA.E_CARACTERIZACION';
    public const MONOTRIBUTO = 'MONOTRIBUTO';
    public const AUTONOMO_MONOTRIBUTO = 'SUPA.E_AUTONOMO_MONOTRIBUTO';
    public const ORGANISMO_INFORMANTE = 'SUPA.E_ORGANISMO_INFORMANTE';
    public const ORGANISMO_CONTROL = 'SUPA.E_ORGANISMO_CONTROL';
    public const TIPO_EMPRESA_JURIDICA = 'SUPA.TIPO_EMPRESA_JURIDICA';
    public const ACTIVIDAD_OCUPACIONAL = 'SUPA.E_ACTIVIDAD_OCUPACIONAL';
    public const RUBRO_ACTIVIDAD = 'SUPA.E_RUBRO_ACTIVIDAD';
    public const ACTIVIDAD_RUBRO = 'SUPA.A_ACTIVIDAD_RUBRO';

    /**
     * Catálogo completo del Anexo 5.1.
     *
     * nombre => [descripción del parámetro, a qué es igual el atributo id]
     *
     * @var array<string, array<int, string>>
     */
    public const CATALOGO = [
        self::ORGANISMO_INFORMANTE => ['Organismos informantes', 'CUIT_ORGANISMO'],
        self::ORGANISMO_CONTROL => ['Organismo de control', 'CUIT_ORGANISMO'],
        'SUPA.A_PROVINCIA_SOCIEDAD_ORG' => ['Relación entre provincia, organismo de control y forma jurídica', 'COD_PROVINCIA, COD_TIPO_EMPRESA_JURIDICA, COD_ORGANISMO (reconstruido con duda: el Anexo corta "SUPA.A_PROVINCIA_SOCIEDAD_O" + "RG")'],
        self::TIPO_EMPRESA_JURIDICA => ['Tipos de forma jurídica', 'COD_TIPO_EMPRESA_JURIDICA'],
        'SUPA.TIPO_EMPRESA_JURI_SUBGRUPO' => ['Identificación particular de la forma jurídica', 'COD_SUBGRUPO_TIPO_EMPJURI'],
        self::PROVINCIA => ['Provincia', 'COD_PROVINCIA'],
        self::TIPO_DATO_ADICIONAL_DOMICILIO => ['Tipo de dato adicional del domicilio', 'COD_TIPO_DATO_ADICIONAL_DOM'],
        self::TIPO_CLAVE_IDENTIFICACION => ['Tipo de clave (CUIT / CUIL / CDI)', 'COD_CLAVE_IDENTIFICACION'],
        self::TIPO_DOCUMENTO => ['Tipo de documento identificatorio', 'COD_TIPO_DOCUMENTO'],
        self::SEXO => ['Clasificación tipo de sexo (M / F / X)', 'COD_TIPO_SEXO'],
        self::TIPO_RESIDENCIA => ['Clasificación tipo de residencia', 'COD_TIPO_RESIDENCIA'],
        'SUPA.TIPO_COMPONENTE_SOCIEDAD' => ['Tipo de componente de sociedad', 'COD_TIPO_COMPONENTE_SOCIEDAD'],
        'SUPA.TIPO_RELACION_COMPONENTE_SOC' => ['Particularidades de los componentes de acuerdo al tipo societario', 'COD_TIPO_COMPONENTE_SOCIEDAD (reconstruido con duda: el Anexo corta "SUPA.TIPO_RELACION_COMPONE" + "NTE_SOC")'],
        'SUPA.TIPO_RELACION' => ['Relaciones que pueden darse entre las claves', 'COD_RELACION'],
        'SUPA.TIPO_SUBTIPO_RELACION' => ['Subtipos de relación entre las claves', 'CODIGO_SUBTIPO_RELACION'],
        self::TIPO_EMAIL => ['Tipo de email', 'COD_TIPO_EMAIL'],
        self::TIPO_DOMICILIO => ['Tipo de domicilio', 'COD_TIPO_DOMICILIO'],
        self::CALLE => ['Calle (sólo CABA)', 'COD_CALLE'],
        self::LOCALIDAD => ['Localidad', ''],
        self::MONOTRIBUTO => ['Monotributo según F.184', 'COD_CATEGORIA_MONOTRIBUTO'],
        self::AUTONOMO_MONOTRIBUTO => ['Tipología de autónomos de monotributo según F.184', 'COD_AUTONOMO_MONOTRIBUTO'],
        self::REGIMEN => ['Nómina de regímenes vigentes y no vigentes', 'COD_IMPUESTO, COD_REGIMEN'],
        self::TIPO_REGIMEN => ['Clasificaciones de regímenes', 'COD_TIPO_REGIMEN'],
        self::CARACTERIZACION => ['Atributos y características de los contribuyentes', 'COD_CARACTERIZACION'],
        'SUPA.TIPO_ARCHIVO' => ['Tipos de documento válidos para el sistema', 'COD_TIPO_ARCHIVO'],
        self::ACTIVIDAD_RUBRO => ['Relaciones entre actividades y rubros de todos los nomencladores', 'COD_ACTIVIDAD:COD_RUBRO:COD_NOMENCLADOR'],
        self::RUBRO_ACTIVIDAD => ['Rubros de los nomencladores de actividades económicas', 'COD_RUBRO:COD_NOMENCLADOR'],
        self::ACTIVIDAD_OCUPACIONAL => ['Actividades ocupacionales no incluidas en nomenclador F.150', 'COD_ACTIVIDAD_OCUPACIONAL'],
    ];

    /**
     * Colecciones que se traen cuando no se pide ninguna en particular.
     *
     * Son los catálogos chicos de identificación del contribuyente: los que el sistema
     * ya usa o va a necesitar para completar a mano lo que ARCA no da. Los pesados
     * (T_CALLE de CABA, T_LOCALIDAD, E_ACTIVIDAD_RUBRO con cientos de miles de filas)
     * quedan afuera a propósito: se piden con --coleccion o con --todo.
     *
     * @var array<int, string>
     */
    public const POR_DEFECTO = [
        self::TIPO_DOCUMENTO,
        self::TIPO_CLAVE_IDENTIFICACION,
        self::PROVINCIA,
        self::TIPO_DOMICILIO,
        self::TIPO_DATO_ADICIONAL_DOMICILIO,
        self::TIPO_EMAIL,
        self::SEXO,
        self::TIPO_RESIDENCIA,
    ];

    /**
     * @return array<int, string>
     */
    public static function nombres(): array
    {
        return array_keys(self::CATALOGO);
    }

    public static function existe(string $nombre): bool
    {
        return isset(self::CATALOGO[$nombre]);
    }

    public static function descripcion(string $nombre): string
    {
        return isset(self::CATALOGO[$nombre]) ? self::CATALOGO[$nombre][0] : '';
    }
}
