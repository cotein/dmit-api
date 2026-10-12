<?php

namespace App\Src\Afip\DatosBase;

/**
 * Mapa de los catálogos de datos base de ARCA: qué archivo va a qué tabla, con
 * qué columnas y cuántas filas de preámbulo hay que saltear en cada uno.
 *
 * POR QUÉ ESTÁ ACÁ Y NO EN EL COMANDO: cada archivo que publica ARCA tiene una
 * cantidad DISTINTA de filas de preámbulo antes del encabezado real —TABLA
 * PROVINCIAS tiene 2, otros_Tributos tiene 1, TABLA-20-PAISES tiene 0 y TABLA
 * UNIDADES DE MEDIDA tiene 1—, así que no se puede asumir una posición fija.
 * Si los offsets vivieran dentro del flujo del comando, agregar un catálogo
 * obligaría a tocar la lógica de importación y a acordarse de qué offset tenía
 * cada archivo. Acá son datos: agregar un catálogo es agregar una entrada.
 *
 * Mismos criterios que DestinosPadron100 (app/Src/Afip/Padron100), para que los
 * dos importadores de catálogos de ARCA del proyecto se lean igual.
 *
 * Los offsets se averiguaron abriendo cada archivo y contando, no deduciendo:
 * ver el encabezado esperado declarado en `cabecera` de cada entrada, que es lo
 * que el lector valida antes de importar.
 */
final class ConfiguracionDatosBase
{
    /** CSV separado por comas, como lo deja LibreOffice al convertir los .xls. */
    const FORMATO_CSV = 'csv';

    /** Texto plano separado por punto y coma (el nomenclador de actividades). */
    const FORMATO_TXT = 'txt';

    /**
     * Alta de filas faltantes y actualización de la descripción cuando difiere.
     * Es el modo de los catálogos donde el archivo de ARCA es la única fuente.
     */
    const MODO_ESCRITURA = 'escritura';

    /**
     * Sólo se dan de alta los códigos que faltan y NUNCA se pisa la descripción
     * existente. Es el modo de `states`, donde el nombre cargado está curado
     * (lo muestran los selects de la aplicación) y ARCA trae erratas propias,
     * como "Catamara" en la TABLA PROVINCIAS. Es el mismo comportamiento que ya
     * usa SyncAfipParametrosCommand con --solo-nuevos y el que DestinosPadron100
     * documenta para las provincias ("no se pisan: sólo se comparan").
     */
    const MODO_ALTA = 'alta';

    /*
     * YA NO EXISTE UN MODO "CRUDO" (era MODO_CRUDO + COLECCION_CRUDA, y con él el
     * prefijo `datos_base.`). Los ocho catálogos que no tenían tabla propia se
     * guardaban en el JSON de `afip_parametros` bajo ese prefijo, y esa tabla es
     * otra cosa: es el almacén CRUDO de lo que devuelve el WS ws_sr_padron_a100
     * (ver su migración y app/Src/Afip/Padron100). Ahora cada catálogo tiene su
     * tabla y este importador no vuelve a escribir ahí. El modo se sacó en vez de
     * dejarlo dormido a propósito: si mañana se agrega un catálogo sin tabla, el
     * escritor lo rechaza (no está en su lista blanca) en vez de que las filas
     * desaparezcan dentro de la tabla comodín sin que nadie lo note.
     */

    /**
     * El mapa completo. La clave es el nombre corto que recibe el comando.
     *
     * Convenciones de las claves de cada entrada:
     *
     *  - `archivo`   nombre del archivo sin extensión (el .csv lo genera la
     *                conversión desde el .xls del mismo nombre en arca-datos-base).
     *  - `preambulo` filas a saltear ANTES del encabezado, contadas abriendo el archivo.
     *  - `cabecera`  encabezado real declarado por posición. `null` = esa posición
     *                viene sin nombre en el archivo de ARCA (pasa en INCOTERMS, cuyo
     *                encabezado es sólo "Código," pero las filas traen dos columnas).
     *                Sirve para validar: si ARCA renombra o agrega columnas, el
     *                importador avisa y no importa.
     *  - `columnas`  mapeo POSICIONAL del archivo al destino (posición => campo).
     *  - `patron`    expresión que tiene que cumplir el código; es lo que descarta
     *                las notas al pie (ver `vouchers`) y las filas basura del final.
     *  - `destino`   tabla, clave natural, columna descriptiva y modo.
     *                `clave` es SIEMPRE el nombre de una columna real de la tabla:
     *                sobre ella va el UNIQUE que hace idempotente al importador.
     *  - `por_defecto` valores que el archivo no trae pero la tabla exige como NOT
     *                NULL en el alta. Se aplican SOLO al insertar: nunca se usan
     *                para pisar una columna que ya tenía valor (ver ImportadorDatosBase).
     *  - `normalizar` correcciones de erratas conocidas de ARCA, por código.
     *  - `codigo_sin_artefactos` saca del código los caracteres que el Excel pegó
     *                adelante (MONEDAS trae "´002" en lugar de "002").
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogos()
    {
        return [

            // 24 provincias. Salta el código 15, igual que la tabla states del
            // proyecto: los dos conjuntos de afip_code son idénticos.
            'provincias' => [
                'archivo' => 'TABLA PROVINCIAS V.0  25082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'afip_code', 1 => 'name'],
                'patron' => '/^\d{1,2}$/',
                'destino' => [
                    'tabla' => 'states',
                    'clave' => 'afip_code',
                    'descripcion' => 'name',
                    'modo' => self::MODO_ALTA,
                ],
                'por_defecto' => [],
                // ARCA escribe "Catamara" en su tabla (así, sin la t). Se corrige
                // acá porque si no la errata entra a la base en el primer alta.
                'normalizar' => ['2' => ['name' => 'Catamarca']],
            ],

            'comprobantes' => [
                'archivo' => 'TABLACOMPROBANTES',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'afip_code', 1 => 'name'],
                'patron' => '/^\d{3}$/',
                'destino' => [
                    'tabla' => 'vouchers',
                    'clave' => 'afip_code',
                    'descripcion' => 'name',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
                // `vouchers.inscription_id` NO se toca: no viene en el archivo y es
                // la columna que usa AfipVoucherController para decidir qué
                // comprobante le corresponde a cada condición frente al IVA.
            ],

            'documentos' => [
                'archivo' => 'TABLA DOC_ID COMPRADOR V.0  25082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'afip_code', 1 => 'name'],
                'patron' => '/^\d{1,2}$/',
                'destino' => [
                    'tabla' => 'afip_documents',
                    'clave' => 'afip_code',
                    'descripcion' => 'name',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],

            'monedas' => [
                'archivo' => 'TABLA MONEDAS V.0  25082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Moneda'],
                'columnas' => [0 => 'code', 1 => 'name'],
                'patron' => '/^[0-9A-Z]{3}$/',
                'destino' => [
                    'tabla' => 'moneys',
                    'clave' => 'code',
                    'descripcion' => 'name',
                    'modo' => self::MODO_ESCRITURA,
                ],
                // symbol, value y description son NOT NULL sin default: la tabla
                // las creó así y el seeder las llena con cadena vacía.
                'por_defecto' => ['symbol' => '', 'value' => '', 'description' => ''],
                // El archivo trae "´002" (con acento agudo pegado del Excel) en vez
                // de "002"; sin esto el código queda con un carácter invisible.
                'normalizar' => [],
                'codigo_sin_artefactos' => true,
            ],

            // 54 unidades de 2 dígitos (00..99) del nomenclador de ARCA.
            'unidades' => [
                'archivo' => 'TABLA UNIDADES DE MEDIDA V.0  25082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 1,
                'cabecera' => ['Codigo', 'Descripción'],
                'columnas' => [0 => 'codigo', 1 => 'descripcion'],
                'patron' => '/^\d{2}$/',
                'destino' => [
                    'tabla' => 'afip_unidades_medida',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],

            'paises' => [
                'archivo' => 'TABLA-20-PAISES-20V.0-20-2028072022',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 0,
                'cabecera' => ['CODIGO', 'DESCRIPCION', 'PAIS'],
                'columnas' => [0 => 'codigo', 1 => 'descripcion', 2 => 'pais'],
                'patron' => '/^\d{3}$/',
                'destino' => [
                    'tabla' => 'afip_paises',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
                // Las dos columnas de texto NO son redundantes: en las zonas
                // francas la descripción agrega el país de origen ("ZF Iquique")
                // mientras que `pais` lo dice solo ("CHILE"). Las dos se importan.
            ],

            'tributos' => [
                'archivo' => 'otros_Tributos',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 1,
                'cabecera' => ['Código', 'Descripción', null],
                'columnas' => [0 => 'codigo', 1 => 'descripcion', 2 => 'observacion'],
                'patron' => '/^\d{1,2}$/',
                'destino' => [
                    'tabla' => 'afip_otros_tributos',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
                // La tercera columna viene sin nombre en el encabezado, pero la
                // fila de título del archivo la llama "Observaciones": es la
                // condición de uso de cada código y se guarda.
            ],

            'incoterms' => [
                'archivo' => 'TABLA INCOTERMS V.0.1  26012011',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                // El encabezado de ARCA nombra sólo la primera columna; la
                // descripción va sin nombre. Por eso la posición 1 es null.
                'cabecera' => ['Código', null],
                'columnas' => [0 => 'codigo', 1 => 'descripcion'],
                'patron' => '/^[A-Z]{3}$/',
                'destino' => [
                    'tabla' => 'afip_incoterms',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],

            // Los 11 códigos de condición frente al IVA que publica ARCA.
            'responsables' => [
                'archivo' => 'TABLA-TIPO-RESPONSABLES-V.0-06022025',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'id', 1 => 'name'],
                'patron' => '/^\d{1,2}$/',
                'destino' => [
                    'tabla' => 'afip_inscriptions',
                    'clave' => 'id',
                    'descripcion' => 'name',
                    'modo' => self::MODO_ESCRITURA,
                ],
                // afip_inscriptions.short es NOT NULL y ARCA no publica siglas. Se
                // usan las del AfipInscriptionSeeder que ya está en el proyecto en
                // vez de inventarlas: son las que la aplicación muestra hoy.
                'por_defecto' => [
                    'short' => [
                        '1' => 'IRI', '4' => 'ISE', '5' => 'CF', '6' => 'M',
                        '7' => 'SNC', '8' => 'PE', '9' => 'CE', '10' => 'IL',
                        '13' => 'MS', '15' => 'INA', '16' => 'MTIP',
                    ],
                ],
                'normalizar' => [],
                // La clave es `id` y NO es un detalle: afip_inscriptions.id es el
                // código de ARCA (Constantes::INSCRIPCION_* y vouchers.inscription_id
                // apuntan ahí). Los 11 códigos de este archivo caen exactamente en
                // los ids 1,4,5,6,7,8,9,10,13,15 y 16 del seeder, así que el alta
                // respeta la numeración que espera la regla de comprobantes.
            ],

            // El cruce operación × condición frente al IVA.
            'operacion_condicion_iva' => [
                'archivo' => 'OperacionCondicionIVA',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción', null],
                'columnas' => [0 => 'codigo', 1 => 'descripcion', 2 => 'observacion'],
                'patron' => '/^\d$/',
                'destino' => [
                    'tabla' => 'afip_operacion_condicion_iva',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
                // La tercera columna viene sin nombre en el encabezado y sólo la
                // trae el código 0 ("NO APLICA PARA FACTURA ELECTRONICA"): es una
                // observación, no un dato que falte.
            ],

            'conceptos' => [
                'archivo' => 'TABLAConceptosIncluidosV.025082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'codigo', 1 => 'descripcion'],
                'patron' => '/^\d$/',
                'destino' => [
                    'tabla' => 'afip_conceptos_incluidos',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],

            'idiomas' => [
                'archivo' => 'TABLAIDIOMASV.025082010',
                'formato' => self::FORMATO_CSV,
                'preambulo' => 2,
                'cabecera' => ['Código', 'Descripción'],
                'columnas' => [0 => 'codigo', 1 => 'descripcion'],
                'patron' => '/^\d$/',
                'destino' => [
                    'tabla' => 'afip_idiomas',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],

            // 958 actividades del nomenclador del formulario F883.
            'actividades' => [
                'archivo' => 'ACTIVIDADES_ECONOMICAS_F883',
                'formato' => self::FORMATO_TXT,
                'separador' => ';',
                // Sin preámbulo: el encabezado de este archivo es la primera línea
                // (a diferencia de los CSV, que traen el título arriba). Se contó
                // abriendo el archivo, no se dedujo del nombre.
                'preambulo' => 0,
                'cabecera' => ['COD_ACTIVIDAD_F883', 'DESC_ACTIVIDAD_F883', 'DESCL_ACTIVIDA_F883', null],
                'columnas' => [0 => 'codigo', 1 => 'descripcion', 2 => 'descripcion_larga'],
                'patron' => '/^\d{6}$/',
                'destino' => [
                    'tabla' => 'afip_actividades_f883',
                    'clave' => 'codigo',
                    'descripcion' => 'descripcion',
                    'modo' => self::MODO_ESCRITURA,
                ],
                'por_defecto' => [],
                'normalizar' => [],
            ],
        ];
    }

    /**
     * Archivos publicados por ARCA que NO se importan, con el motivo.
     *
     * `TABLA-TIPO-RESPONSABLES-V.0-06022025 (1)` es una copia byte a byte del mismo
     * archivo sin el "(1)" (los dos pesan 7168 bytes y dan 11 filas idénticas).
     * Importarlo sería trabajar dos veces sobre la misma tabla: se deja listado acá
     * para que quede constancia y para que --todos no lo levante por accidente.
     *
     * @return array<string, string>
     */
    public static function ignorados()
    {
        return [
            'TABLA-TIPO-RESPONSABLES-V.0-06022025 (1)' => 'copia duplicada de TABLA-TIPO-RESPONSABLES-V.0-06022025 (la importa el catálogo `responsables`)',
            'TABLA-TIPO-RESPONSABLES-V.0-06022025 (1).xls' => 'copia duplicada de TABLA-TIPO-RESPONSABLES-V.0-06022025 (la importa el catálogo `responsables`)',
        ];
    }

    /**
     * Entrada de un catálogo, o null si el nombre no existe.
     *
     * @return array<string, mixed>|null
     */
    public static function catalogo($nombre)
    {
        $catalogos = self::catalogos();

        return isset($catalogos[$nombre]) ? $catalogos[$nombre] : null;
    }

    /**
     * Nombres de todos los catálogos, en el orden en que se importan.
     *
     * El orden importa: primero los que van a tablas que el sistema ya consume
     * (states, vouchers, afip_documents, moneys, afip_inscriptions) y después los
     * nomencladores que ARCA publica en archivo y que todavía no tienen consumidor
     * (paises, unidades, tributos, incoterms, idiomas y compañía). Es el orden en
     * que los muestra --todos.
     *
     * @return array<int, string>
     */
    public static function nombres()
    {
        return array_keys(self::catalogos());
    }

    /**
     * Tablas donde este importador escribe. Es una lista cerrada a propósito,
     * igual que DestinosPadron100::tablasEscribibles(): el nombre de tabla nunca
     * sale de un archivo ni de un argumento, así que no puede venir de afuera.
     *
     * `afip_parametros` NO está en la lista desde que cada catálogo tiene su tabla:
     * esa tabla es del WS ws_sr_padron_a100 (app/Src/Afip/Padron100) y éste es el
     * importador de archivos. Que no esté acá es lo que hace imposible que este
     * comando vuelva a escribir en ella por descuido.
     *
     * @return array<int, string>
     */
    public static function tablasDestino()
    {
        return [
            'states',
            'vouchers',
            'afip_documents',
            'moneys',
            'afip_inscriptions',
            'afip_paises',
            'afip_actividades_f883',
            'afip_unidades_medida',
            'afip_otros_tributos',
            'afip_incoterms',
            'afip_operacion_condicion_iva',
            'afip_conceptos_incluidos',
            'afip_idiomas',
        ];
    }

    /**
     * Extensión del archivo según el formato declarado.
     */
    public static function extension(array $catalogo)
    {
        return $catalogo['formato'] === self::FORMATO_TXT ? '.txt' : '.csv';
    }
}
