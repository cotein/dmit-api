<?php

namespace App\Console\Commands;

use App\Src\Afip\DatosBase\ConfiguracionDatosBase;
use App\Src\Afip\DatosBase\EscritorDatosBaseLaravel;
use App\Src\Afip\DatosBase\ImportadorDatosBase;
use Illuminate\Console\Command;

/**
 * Carga los catálogos de datos base de ARCA (las tablas clásicas de AFIP).
 *
 *   php artisan afip:import-datos-base --todos
 *   php artisan afip:import-datos-base provincias
 *   php artisan afip:import-datos-base responsables --dry-run
 *   php artisan afip:import-datos-base actividades --file=/ruta/ACTIVIDADES_ECONOMICAS_F883.txt
 *
 * DE DÓNDE SALEN LOS ARCHIVOS: el dueño los dejó en arca-datos-base/ como .xls y
 * .xlsx viejos. El backend NO tiene ninguna librería de Excel (no hay phpoffice ni
 * maatwebsite en composer.json) y no se agrega ninguna: se leen CSV. La conversión
 * se hace una vez, aparte, con LibreOffice:
 *
 *   cd arca-datos-base && mkdir -p /tmp/arca-csv && \
 *   timeout 400 libreoffice --headless -env:UserInstallation=file:///tmp/lo-profile \
 *     --convert-to csv --outdir /tmp/arca-csv *.xls *.xlsx
 *
 * El -env:UserInstallation es imprescindible: sin eso LibreOffice falla en
 * silencio y no escribe nada. El .txt de actividades no se convierte, se copia.
 *
 * CON QUÉ SE LO APUNTA: --dir apunta al directorio con los CSV (por defecto
 * ARCA_DATOS_BASE_CSV del .env, o el temporal /tmp/arca-csv). --file reemplaza un
 * solo archivo y sirve para reprocesar una entrega suelta.
 *
 * QUÉ ESCRIBE: a las tablas que ya existían —states, vouchers, afip_documents,
 * moneys y afip_inscriptions— y a las ocho tablas propias de los catálogos que
 * antes se guardaban crudos en afip_parametros (afip_paises, afip_actividades_f883,
 * afip_unidades_medida, afip_otros_tributos, afip_incoterms,
 * afip_operacion_condicion_iva, afip_conceptos_incluidos y afip_idiomas). Este
 * comando NO crea ninguna tabla: si falta una, la salta y lo dice.
 *
 * afip_parametros NO se toca desde acá: es el almacén crudo del WS
 * ws_sr_padron_a100 (ver app/Src/Afip/Padron100).
 */
class ImportAfipDatosBase extends Command
{
    protected $signature = 'afip:import-datos-base
                            {catalogo? : Catálogo a importar (sin esto, lista los disponibles)}
                            {--dir= : Directorio con los CSV convertidos}
                            {--file= : Ruta a un archivo puntual; requiere un solo catálogo}
                            {--dry-run : Informa qué haría sin escribir nada}
                            {--todos : Importa todos los catálogos de una}';

    protected $description = 'Importa los catálogos de datos base de ARCA (provincias, comprobantes, documentos, monedas, condiciones de IVA, actividades y demás)';

    public function handle(): int
    {
        if (! $this->option('todos') && ! $this->argument('catalogo')) {
            $this->listarCatalogs();
            $this->line('');
            $this->warn('Pasá un catálogo, o --todos para importarlos todos.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $nombres = $this->nombres();

        if ($nombres === null) {
            return self::FAILURE;
        }

        $escritor = new EscritorDatosBaseLaravel();
        $informes = [];

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Catálogos: ' . implode(', ', $nombres));

        foreach ($nombres as $nombre) {
            $catalogo = ConfiguracionDatosBase::catalogo($nombre);
            $ruta = $this->rutaDe($catalogo);

            $informe = ImportadorDatosBase::importar($catalogo, $ruta, $escritor, $dryRun);
            $informe['catalogo'] = $nombre;
            $informes[] = $informe;
        }

        $this->informar($informes, $dryRun);

        return $this->resumen($informes, $dryRun);
    }

    /**
     * Catálogos pedidos: el argumento con --todos, o el de siempre.
     *
     * @return array<int, string>|null  null si lo pedido no existe
     */
    private function nombres()
    {
        $pedido = $this->argument('catalogo');

        if ($this->option('todos')) {
            return ConfiguracionDatosBase::nombres();
        }

        if (ConfiguracionDatosBase::catalogo($pedido) === null) {
            $this->error("No existe el catálogo '{$pedido}'.");

            return null;
        }

        return [$pedido];
    }

    /**
     * Ruta del archivo del catálogo, ya resuelta.
     */
    private function rutaDe(array $catalogo)
    {
        $file = $this->option('file');

        if ($file) {
            return (string) $file;
        }

        return rtrim($this->directorio(), '/') . '/' . $catalogo['archivo'] . ConfiguracionDatosBase::extension($catalogo);
    }

    /**
     * Directorio de los CSV convertidos.
     *
     * El valor por defecto es temporal a propósito: los .xls originales están en
     * arca-datos-base/ y NO se pueden leer sin LibreOffice, así que el comando no
     * puede apuntar ahí. El docblock de la clase explica cómo regenerarlos.
     */
    private function directorio()
    {
        $dir = $this->option('dir');

        if ($dir) {
            return (string) $dir;
        }

        $env = env('ARCA_DATOS_BASE_CSV');

        return $env ? (string) $env : sys_get_temp_dir() . '/arca-csv';
    }

    /**
     * Tabla de resultados, con el mismo formato que SyncAfipParametrosCommand.
     *
     * @param  array<int, array<string, mixed>>  $informes
     */
    private function informar(array $informes, $dryRun)
    {
        $filas = [];

        foreach ($informes as $informe) {
            $filas[] = [
                $informe['catalogo'],
                $informe['tabla'],
                $informe['preambulo'],
                $informe['leidas'],
                $informe['nuevas'],
                $dryRun && $informe['error'] === null ? $informe['nuevas'] : $informe['actualizadas'],
                $informe['sin_cambios'],
                $informe['respetadas'],
                count($informe['salteadas']),
                count($informe['sobrantes']),
                $informe['error'] === null ? 'ok' : 'ERROR',
            ];
        }

        $this->table(
            ['Catálogo', 'Tabla', 'Saltea', 'Leídas', 'Nuevas', ($dryRun ? 'Cambiarían' : 'Actualizadas'), 'Sin cambios', 'Respetadas', 'Salteadas', 'Sobrantes', 'Estado'],
            $filas
        );

        foreach ($informes as $informe) {
            if ($informe['error'] !== null) {
                $this->error($informe['catalogo'] . ': ' . $informe['error']);

                foreach ($informe['problemas'] as $problema) {
                    $this->line('    ' . $problema);
                }

                continue;
            }

            // El encabezado real que se detectó y el preámbulo salteado: es lo que
            // hay que mirar cuando un offset se corre, porque si el archivo cambia
            // de forma entran filas de título como si fueran datos.
            $this->line('  ' . $informe['catalogo'] . ': salteé ' . $informe['preambulo'] . ' fila(s); encabezado detectado: '
                . $this->encabezado($informe['cabecera']) . ($informe['bom'] ? ' [traía BOM]' : ''));

            foreach (array_slice($informe['salteadas'], 0, 10) as $motivo) {
                $this->line('    salteada ' . $motivo);
            }

            if (count($informe['salteadas']) > 10) {
                $this->line('    ... y ' . (count($informe['salteadas']) - 10) . ' más');
            }

            foreach (array_slice($informe['diferencias'], 0, 24) as $diferencia) {
                $this->line('    descripción distinta (no se pisa) ' . $diferencia);
            }

            if ($informe['sobrantes'] !== []) {
                $this->line('    en ' . $informe['tabla'] . ' hay ' . count($informe['sobrantes'])
                    . ' código(s) que el archivo no trae (no se borra nada): ' . implode(', ', array_slice($informe['sobrantes'], 0, 20)));
            }

            foreach ($informe['colisiones'] as $colision) {
                $this->warn('    claves equivalentes en ' . $informe['tabla'] . ': ' . $colision);
            }
        }

        foreach (ConfiguracionDatosBase::ignorados() as $archivo => $motivo) {
            $this->line('  sin usar: ' . $archivo . ' — ' . $motivo);
        }
    }

    /**
     * @param  array<int, string>  $cabecera
     */
    private function encabezado(array $cabecera)
    {
        $partes = [];

        foreach ($cabecera as $i => $celda) {
            $partes[] = $celda === '' ? '(sin nombre, col ' . ($i + 1) . ')' : $celda;
        }

        return implode(' | ', $partes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $informes
     */
    private function resumen(array $informes, $dryRun)
    {
        $resumen = ImportadorDatosBase::resumir($informes);

        $this->info(sprintf(
            '%sLeídos %d · nuevos %d · actualizados %d · sin cambios %d · respetadas %d · salteadas %d · errores %d',
            $dryRun ? '[dry-run] ' : '',
            $resumen['leidas'],
            $resumen['nuevas'],
            $resumen['actualizadas'],
            $resumen['sin_cambios'],
            $resumen['respetadas'],
            $resumen['salteadas'],
            $resumen['con_error']
        ));

        if ($dryRun) {
            $this->warn('[dry-run] No se escribió nada. Los conteos de arriba son lo que haría una corrida real.');
        }

        return $resumen['con_error'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Lista los catálogos con su archivo, tabla destino y offset.
     */
    private function listarCatalogs()
    {
        $filas = [];

        foreach (ConfiguracionDatosBase::catalogos() as $nombre => $catalogo) {
            $filas[] = [
                $nombre,
                $catalogo['archivo'] . ConfiguracionDatosBase::extension($catalogo),
                $catalogo['preambulo'],
                $catalogo['destino']['tabla'],
                $catalogo['destino']['modo'],
            ];
        }

        $this->table(['Catálogo', 'Archivo', 'Filas a saltear', 'Tabla destino', 'Modo'], $filas);
    }
}
