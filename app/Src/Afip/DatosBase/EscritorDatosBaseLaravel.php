<?php

namespace App\Src\Afip\DatosBase;

use Illuminate\Support\Facades\DB;

/**
 * Escritor real: escribe en las tablas del sistema.
 *
 * Cada catálogo tiene UNA tabla propia y las columnas de la fila mapeada son
 * columnas reales de esa tabla. Ya no hay un destino "crudo": los ocho catálogos
 * que no tenían tabla (paises, actividades, unidades de medida, tributos,
 * incoterms, operación×condición de IVA, conceptos e idiomas) se guardaban en el
 * JSON de `afip_parametros`, y esa tabla quedó para lo que fue creada: el volcado
 * crudo de las colecciones del WS ws_sr_padron_a100 (app/Src/Afip/Padron100).
 *
 * Los timestamps los pone este escritor: el importador no sabe de `now()`.
 */
final class EscritorDatosBaseLaravel implements EscritorDatosBase
{
    /**
     * Columnas que el importador puede escribir por tabla.
     *
     * Es una lista cerrada a propósito, igual que en DestinosPadron100: ni el
     * nombre de la tabla ni el de la columna pueden venir de un archivo o de un
     * argumento de consola, así que no hay forma de que un archivo de ARCA
     * escriba en una columna que no le corresponde. La clave de la lista es
     * también la verificación de que la tabla destino existe y es la esperada.
     *
     * @var array<string, array<int, string>>
     */
    private static $columnasPermitidas = [
        'states' => ['afip_code', 'name'],
        'vouchers' => ['afip_code', 'name'],
        'afip_documents' => ['afip_code', 'name'],
        'moneys' => ['code', 'name', 'symbol', 'value', 'description'],
        'afip_inscriptions' => ['id', 'name', 'short'],
        'afip_paises' => ['codigo', 'descripcion', 'pais'],
        'afip_actividades_f883' => ['codigo', 'descripcion', 'descripcion_larga'],
        'afip_unidades_medida' => ['codigo', 'descripcion'],
        'afip_otros_tributos' => ['codigo', 'descripcion', 'observacion'],
        'afip_incoterms' => ['codigo', 'descripcion'],
        'afip_operacion_condicion_iva' => ['codigo', 'descripcion', 'observacion'],
        'afip_conceptos_incluidos' => ['codigo', 'descripcion'],
        'afip_idiomas' => ['codigo', 'descripcion'],
    ];

    /**
     * @param  string  $tabla
     * @return bool
     */
    public function tablaExiste($tabla)
    {
        if (! in_array($tabla, ConfiguracionDatosBase::tablasDestino(), true)) {
            return false;
        }

        return DB::getSchemaBuilder()->hasTable($tabla);
    }

    /**
     * @param  array<string, mixed>  $destino
     * @return array<string, array{clave: string, descripcion: string, valores: array<string, string>}>
     */
    public function instantanea(array $destino)
    {
        $clave = $destino['clave'];
        $descripcion = $destino['descripcion'];
        $instantanea = [];

        foreach (DB::table($destino['tabla'])->get() as $fila) {
            $fila = (array) $fila;
            $claveReal = (string) $fila[$clave];
            $valores = [];

            foreach ($fila as $columna => $valor) {
                // created_at / updated_at cambian solos y no son parte de la huella.
                if ($columna === 'created_at' || $columna === 'updated_at') {
                    continue;
                }

                $valores[$columna] = $valor === null ? '' : (string) $valor;
            }

            $instantanea[LectorDatosBase::normalizar($claveReal)] = [
                'clave' => $claveReal,
                'descripcion' => isset($fila[$descripcion]) ? (string) $fila[$descripcion] : '',
                'valores' => $valores,
            ];
        }

        return $instantanea;
    }

    /**
     * @param  array<string, mixed>  $destino
     * @param  array<string, string>  $columnas
     */
    public function insertar(array $destino, array $columnas)
    {
        $columnas = $this->soloPermitidas($destino['tabla'], $columnas);

        $columnas['created_at'] = now();
        $columnas['updated_at'] = now();

        DB::table($destino['tabla'])->insert($columnas);
    }

    /**
     * @param  array<string, mixed>  $destino
     * @param  string  $claveReal
     * @param  array<string, string>  $columnas
     */
    public function actualizar(array $destino, $claveReal, array $columnas)
    {
        $columnas = $this->soloPermitidas($destino['tabla'], $columnas);

        $columnas['updated_at'] = now();

        DB::table($destino['tabla'])
            ->where($destino['clave'], $claveReal)
            ->update($columnas);
    }

    /**
     * Descarta cualquier columna que no esté en la lista blanca de su tabla.
     *
     * @param  array<string, string>  $columnas
     * @return array<string, string>
     */
    private function soloPermitidas($tabla, array $columnas)
    {
        $permitidas = isset(self::$columnasPermitidas[$tabla]) ? self::$columnasPermitidas[$tabla] : [];

        return array_intersect_key($columnas, array_flip($permitidas));
    }
}
