<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Src\Afip\Padron100\AlmacenPadron100;
use App\Src\Afip\Padron100\ClientePadron100;
use App\Src\Afip\Padron100\ColeccionesPadron100;
use App\Src\Afip\Padron100\AutenticacionPadron100;
use App\Src\Afip\Padron100\ClienteSoapPadron100;
use App\Src\Afip\Padron100\ClienteFixturePadron100;
use App\Src\Afip\Padron100\SincronizadorPadron100;

/**
 * Trae los catálogos de ARCA con el WS ws_sr_padron_a100 y los guarda.
 *
 *   php artisan afip:sync-padron-parametros                      # los catálogos chicos de siempre
 *   php artisan afip:sync-padron-parametros --coleccion=SUPA.E_PROVINCIA
 *   php artisan afip:sync-padron-parametros --todo --dry-run     # qué cambiaría, sin escribir
 *   php artisan afip:sync-padron-parametros --fixture=tests/Fixtures/Afip/Padron100
 *
 * El token y la firma los emite el WSAA por el mismo mecanismo que usa el facturador
 * electrónico (AutenticacionPadron100 extiende el WebService del paquete cotein/api-afip
 * pidiendo el servicio ws_sr_padron_a100): el certificado y la clave privada del emisor
 * tienen que estar donde siempre.
 *
 * Todos los datos se guardan crudos en afip_parametros. Además, las colecciones con
 * destino declarado en DestinosPadron100 alimentan la tabla que ya existe:
 * SUPA.TIPO_DOCUMENTO -> afip_documents (los tipos de documento que hoy están a mano) y
 * SUPA.E_PROVINCIA -> contraste contra states, sin escribir nada.
 */
class SyncAfipParametrosCommand extends Command
{
    protected $signature = 'afip:sync-padron-parametros
                            {--coleccion=* : Colección del Anexo 5.1 (repetible). Sin esto, los catálogos chicos por defecto}
                            {--todo : Trae todas las colecciones del catálogo, incluidas las pesadas (T_CALLE, T_LOCALIDAD, E_ACTIVIDAD_RUBRO)}
                            {--empresa= : ID de la empresa emisora; de ahí sale el CUIT representado. Sin esto se usa WS_AFIP_CUIT_REPRESENTADA}
                            {--ambiente=testing : testing | production}
                            {--fixture= : Directorio con XML de respuesta, para probar sin certificado (no llama a ARCA)}
                            {--solo-nuevos : En los catálogos destino sólo da de alta los códigos que faltan, sin pisar descripciones}
                            {--dry-run : Muestra qué cambiaría sin escribir nada}';

    protected $description = 'Sincroniza los catálogos de parámetros de ARCA (ws_sr_padron_a100) hacia las tablas del sistema';

    public function handle(): int
    {
        $colecciones = $this->colecciones();

        if ($colecciones === []) {
            $this->error('No hay nada para sincronizar.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $soloNuevos = (bool) $this->option('solo-nuevos');

        $almacen = new AlmacenPadron100(DB::connection()->getPdo());

        if (! $almacen->tablaParametrosExiste()) {
            $this->error('Falta la tabla afip_parametros: corré la migración create_afip_parametros_table.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Colecciones: ' . implode(', ', $colecciones));

        try {
            $cliente = $this->cliente();
        } catch (\Throwable $e) {
            $this->error('No se pudo preparar el cliente de ARCA: ' . $e->getMessage());

            return self::FAILURE;
        }

        $informes = (new SincronizadorPadron100($cliente, $almacen))->sincronizar($colecciones, $dryRun, $soloNuevos);

        $this->informar($informes, $dryRun);

        return $this->resumen($informes);
    }

    /**
     * @return array<int, string>
     */
    private function colecciones(): array
    {
        $pedidas = array_values(array_filter(array_map('trim', (array) $this->option('coleccion'))));

        if ($pedidas !== []) {
            return array_values(array_unique($pedidas));
        }

        if ((bool) $this->option('todo')) {
            return ColeccionesPadron100::nombres();
        }

        return ColeccionesPadron100::POR_DEFECTO;
    }

    private function cliente(): ClientePadron100
    {
        $fixture = $this->option('fixture');

        if ($fixture) {
            // Aviso fuerte: con --fixture los datos salen de un archivo, no de ARCA.
            $this->warn('MODO PRUEBA: las respuestas salen de ' . $fixture . ' (no se llama a ARCA). No lo uses sobre datos reales.');

            return new ClienteFixturePadron100((string) $fixture);
        }

        $ambiente = strtoupper((string) $this->option('ambiente')) === ClienteSoapPadron100::PRODUCTION
            ? ClienteSoapPadron100::PRODUCTION
            : ClienteSoapPadron100::TESTING;

        $empresa = $this->empresa();
        $cuit = $empresa !== null && $empresa->afip_number ? (string) $empresa->afip_number : null;

        if ($cuit === null) {
            $cuit = env('WS_AFIP_CUIT_REPRESENTADA');
        }

        if (! $cuit) {
            throw new \RuntimeException(
                'No hay CUIT representado: pasá --empresa o definí WS_AFIP_CUIT_REPRESENTADA en el .env.'
            );
        }

        // El ticket de acceso se pide y se guarda con el mismo circuito del facturador.
        $autenticacion = new AutenticacionPadron100(
            $ambiente,
            (string) $cuit,
            $empresa === null ? null : (int) $empresa->id,
            $this->usuario($empresa)
        );

        $this->line('Entorno: ' . $ambiente . ' · CUIT representado: ' . $cuit);

        return new ClienteSoapPadron100($autenticacion, $ambiente);
    }

    /**
     * La empresa emisora: la que se pasa por --empresa. El CUIT representado de ARCA tiene
     * que ser el de la empresa que pide el ticket.
     */
    private function empresa(): ?Company
    {
        $id = (int) $this->option('empresa');

        return $id > 0 ? Company::query()->find($id) : null;
    }

    /**
     * El paquete guarda el token en afip_tokens con company_id y user_id. En consola no hay
     * usuario autenticado, así que se usa el primero de la empresa (o de la base) y, si no
     * hay ninguno, queda en null: esas columnas son opcionales.
     */
    private function usuario(?Company $empresa): ?int
    {
        if ($empresa !== null) {
            $id = $empresa->users()->orderBy('users.id')->value('users.id');

            if ($id !== null) {
                return (int) $id;
            }
        }

        $id = User::query()->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $informes
     */
    private function informar(array $informes, bool $dryRun): void
    {
        $filas = [];

        foreach ($informes as $informe) {
            $destino = is_array($informe['destino'])
                ? $informe['destino']['tabla'] . ' (+' . $informe['destino']['nuevos'] . '/~' . $informe['destino']['actualizados'] . ')'
                : (is_array($informe['contraste']) ? 'contraste states' : '—');

            $filas[] = [
                $informe['coleccion'],
                $informe['informada'] === '' ? '—' : $informe['informada'],
                $informe['leidos'],
                $informe['nuevos'],
                $informe['actualizados'],
                $informe['sinCambios'],
                count($informe['sobrantes']),
                $destino,
                $informe['error'] === null ? 'ok' : 'ERROR',
            ];
        }

        $this->table(
            ['Colección', 'ARCA informó', 'Leídos', 'Nuevos', ($dryRun ? 'Cambiarían' : 'Actualizados'), 'Sin cambios', 'Sobrantes', 'Destino', 'Estado'],
            $filas
        );

        foreach ($informes as $informe) {
            if ($informe['error'] !== null) {
                $this->error($informe['coleccion'] . ': ' . $informe['error']);

                continue;
            }

            foreach ($informe['avisos'] as $aviso) {
                $this->warn($informe['coleccion'] . ': ' . $aviso);
            }

            if (is_array($informe['destino']) && $informe['destino']['sobrantes'] !== []) {
                $this->line('  ' . $informe['coleccion'] . ': en ' . $informe['destino']['tabla'] . ' hay ' . count($informe['destino']['sobrantes'])
                    . ' código(s) que ARCA no devolvió (no se borra nada): ' . implode(', ', array_slice($informe['destino']['sobrantes'], 0, 20)));
            }

            if (is_array($informe['contraste'])) {
                $this->informarContraste($informe['coleccion'], $informe['contraste']);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $contraste
     */
    private function informarContraste(string $coleccion, array $contraste): void
    {
        $this->line('  ' . $coleccion . ': coinciden ' . $contraste['coinciden'] . ' · faltantes ' . count($contraste['faltantes'])
            . ' · con nombre distinto ' . count($contraste['distintas']) . ' · sobrantes ' . count($contraste['sobrantes'])
            . ' (sólo se informa: la tabla states no se toca)');

        foreach (array_slice($contraste['distintas'], 0, 24) as $diferencia) {
            $this->line('    ' . $diferencia['codigo'] . ': ARCA "' . $diferencia['arca'] . '" vs local "' . $diferencia['local'] . '"');
        }

        foreach (array_slice($contraste['faltantes'], 0, 24) as $faltante) {
            $this->line('    falta el código ' . $faltante['codigo'] . ' (' . $faltante['nombre'] . ')');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $informes
     */
    private function resumen(array $informes): int
    {
        $resumen = SincronizadorPadron100::resumir($informes);

        $this->info(sprintf(
            'Leídos %d · nuevos %d · actualizados %d · sin cambios %d · catálogos destino: +%d/~%d · errores %d',
            $resumen['leidos'],
            $resumen['nuevos'],
            $resumen['actualizados'],
            $resumen['sinCambios'],
            $resumen['destinoNuevos'],
            $resumen['destinoActualizados'],
            $resumen['conError']
        ));

        return $resumen['conError'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
