<?php

declare(strict_types=1);

namespace App\Src\Afip\Padron100;

use App\Src\Afip\Padron100\Excepciones\Padron100Exception;

/**
 * Sincronización de colecciones de ARCA hacia las tablas del sistema.
 *
 * Orden por colección:
 *   1. traer la colección (cliente del WS);
 *   2. guardar los parámetros crudos en afip_parametros;
 *   3. si la colección tiene destino declarado, cargarlo (o sólo contrastarlo, como las
 *      provincias).
 *
 * Idempotencia: no se "re-escribe todo y listo". Se compara contra lo que ya hay y sólo
 * se insertan/actualizan las filas con contenido distinto (ComparadorPadron100). Una
 * segunda corrida con la misma respuesta da 0 nuevos, 0 actualizados y todo en
 * sin cambios, sin tocar la base (por eso sincronizado_at no cuenta como cambio).
 *
 * Con $dryRun la única diferencia es que no se llama al almacén: el informe es el mismo.
 */
final class SincronizadorPadron100
{
    /** @var ClientePadron100 */
    private $cliente;

    /** @var AlmacenPadron100 */
    private $almacen;

    /** @var ComparadorPadron100 */
    private $comparador;

    public function __construct(ClientePadron100 $cliente, AlmacenPadron100 $almacen, ?ComparadorPadron100 $comparador = null)
    {
        $this->cliente = $cliente;
        $this->almacen = $almacen;
        $this->comparador = $comparador === null ? new ComparadorPadron100() : $comparador;
    }

    /**
     * @param  array<int, string>  $colecciones
     * @param  bool  $dryRun  Informa sin escribir nada
     * @param  bool  $soloNuevos  En los catálogos destino, no pisa descripciones existentes
     * @return array<int, array<string, mixed>>  Un informe por colección
     */
    public function sincronizar(array $colecciones, bool $dryRun = false, bool $soloNuevos = false): array
    {
        $informes = [];
        $momento = date('Y-m-d H:i:s');

        foreach ($colecciones as $coleccion) {
            $informes[] = $this->sincronizarUna($coleccion, $momento, $dryRun, $soloNuevos);
        }

        return $informes;
    }

    /**
     * @return array<string, mixed>
     */
    private function sincronizarUna(string $coleccion, string $momento, bool $dryRun, bool $soloNuevos): array
    {
        $informe = [
            'coleccion' => $coleccion,
            'descripcion' => ColeccionesPadron100::descripcion($coleccion),
            'enCatalogo' => ColeccionesPadron100::existe($coleccion),
            'informada' => '',
            'nombreCoincide' => true,
            'leidos' => 0,
            'nuevos' => 0,
            'actualizados' => 0,
            'sinCambios' => 0,
            'sobrantes' => [],
            'avisos' => [],
            'destino' => null,
            'contraste' => null,
            'escrito' => false,
            'error' => null,
        ];

        try {
            $respuesta = $this->cliente->traerColeccion($coleccion);
        } catch (Padron100Exception $e) {
            // Una colección que falla no se lleva puesta a las demás: queda en el informe.
            $informe['error'] = $e->getMessage();

            return $informe;
        }

        $informe['informada'] = $respuesta->informada();
        $informe['nombreCoincide'] = $respuesta->nombreCoincide();
        $informe['leidos'] = $respuesta->cantidad();
        $informe['avisos'] = $respuesta->avisos();

        if (! $informe['enCatalogo']) {
            $informe['avisos'][] = 'La colección no está en el catálogo del Anexo 5.1: se guarda igual, pero conviene revisar el nombre.';
        }

        if (! $respuesta->nombreCoincide()) {
            // El ejemplo del manual ya hace esto (pide SUPA.TIPO_TELEFONO y contesta
            // SUPA.E_PROVINCIA). Queda escrito en el informe y no se asume nada.
            $informe['avisos'][] = 'ARCA informó <name>' . $respuesta->informada() . '</name> en lugar de ' . $coleccion
                . ': los datos se guardan bajo la colección pedida, revisá que sea la tabla correcta.';
        }

        $existentes = $this->almacen->parametrosDe($coleccion);
        $plan = $this->comparador->compararParametros($coleccion, $respuesta->parametros(), $existentes, $momento);

        $informe['nuevos'] = count($plan['nuevos']);
        $informe['actualizados'] = count($plan['actualizados']);
        $informe['sinCambios'] = $plan['sinCambios'];
        $informe['sobrantes'] = $plan['sobrantes'];

        if (! $dryRun) {
            $this->almacen->insertarParametros($plan['nuevos']);
            $this->almacen->actualizarParametros($plan['actualizados']);
            $informe['escrito'] = true;
        }

        $informe['destino'] = $this->cargarDestino($coleccion, $respuesta, $momento, $dryRun, $soloNuevos);
        $informe['contraste'] = $this->contrastar($coleccion, $respuesta);

        return $informe;
    }

    /**
     * Carga el catálogo destino, si la colección tiene uno declarado y es de escritura.
     *
     * @return array<string, mixed>|null
     */
    private function cargarDestino(string $coleccion, ColeccionPadron100 $respuesta, string $momento, bool $dryRun, bool $soloNuevos): ?array
    {
        $destino = DestinosPadron100::destinoDe($coleccion);

        if ($destino === null || $destino['modo'] !== DestinosPadron100::MODO_ESCRITURA) {
            return null;
        }

        $deseados = [];

        foreach ($respuesta->parametros() as $parametro) {
            $codigo = DestinosPadron100::valor($destino, 'codigo', $parametro);

            if ($codigo === '') {
                continue;
            }

            $deseados[$codigo] = DestinosPadron100::valor($destino, 'nombre', $parametro);
        }

        $existentes = $this->almacen->existentesDe($destino['tabla']);
        $plan = $this->comparador->compararDestino($deseados, $existentes);

        // Con --solo-nuevos los códigos que ya existen se dejan como están (sirve para no
        // pisar descripciones curadas a mano la primera vez).
        $actualizados = $soloNuevos ? [] : $plan['actualizados'];

        if (! $dryRun) {
            $this->almacen->insertarDestino($destino['tabla'], $plan['nuevos'], $momento);
            $this->almacen->actualizarDestino($destino['tabla'], $actualizados, $momento);
        }

        return [
            'tabla' => $destino['tabla'],
            'nuevos' => count($plan['nuevos']),
            'actualizados' => count($actualizados),
            'sinCambios' => $plan['sinCambios'],
            'sobrantes' => $plan['sobrantes'],
            'soloNuevos' => $soloNuevos,
        ];
    }

    /**
     * Contraste de las provincias contra la tabla states (no escribe: las provincias ya
     * vienen del módulo de ARBA y no se pisan).
     *
     * @return array<string, mixed>|null
     */
    private function contrastar(string $coleccion, ColeccionPadron100 $respuesta): ?array
    {
        $destino = DestinosPadron100::destinoDe($coleccion);

        if ($destino === null || $destino['modo'] !== DestinosPadron100::MODO_CONTRASTE) {
            return null;
        }

        $deseados = [];

        foreach ($respuesta->parametros() as $parametro) {
            $codigo = DestinosPadron100::valor($destino, 'codigo', $parametro);

            if ($codigo !== '') {
                $deseados[$codigo] = DestinosPadron100::valor($destino, 'nombre', $parametro);
            }
        }

        return $this->comparador->contrastarProvincias($deseados, $this->almacen->provinciasExistentes());
    }

    /**
     * Resumen numérico de un conjunto de informes, para la salida del comando.
     *
     * @param  array<int, array<string, mixed>>  $informes
     * @return array<string, int>
     */
    public static function resumir(array $informes): array
    {
        $resumen = [
            'colecciones' => count($informes),
            'leidos' => 0,
            'nuevos' => 0,
            'actualizados' => 0,
            'sinCambios' => 0,
            'conError' => 0,
            'conNombreDistinto' => 0,
            'destinoNuevos' => 0,
            'destinoActualizados' => 0,
        ];

        foreach ($informes as $informe) {
            $resumen['leidos'] += (int) $informe['leidos'];
            $resumen['nuevos'] += (int) $informe['nuevos'];
            $resumen['actualizados'] += (int) $informe['actualizados'];
            $resumen['sinCambios'] += (int) $informe['sinCambios'];
            $resumen['conError'] += $informe['error'] === null ? 0 : 1;
            $resumen['conNombreDistinto'] += $informe['nombreCoincide'] ? 0 : 1;

            if (is_array($informe['destino'])) {
                $resumen['destinoNuevos'] += (int) $informe['destino']['nuevos'];
                $resumen['destinoActualizados'] += (int) $informe['destino']['actualizados'];
            }
        }

        return $resumen;
    }
}
