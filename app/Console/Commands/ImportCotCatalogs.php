<?php

namespace App\Console\Commands;

use App\Models\ArbaNomenclador;
use App\Models\CotMapeoUnidadNomenclador;
use App\Models\CotUnidadMedida;
use App\Src\Arba\Cot\CotProvincias;
use App\Src\Arba\Cot\NomencladorSqlParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga los catálogos que ARBA exige para armar un COT.
 *
 *   php artisan cot:import-catalogs provincias
 *   php artisan cot:import-catalogs nomenclador --file=/ruta/arba_nomenclador.sql
 *   php artisan cot:import-catalogs unidades    --file=/ruta/unidades.csv
 *
 * El nomenclador se importa del script SQL que publica ARBA (7.340 filas: 96
 * capítulos, 2.098 rubros y 5.146 variedades). El nivel VARIEDAD es el que
 * aporta los códigos de 6 dígitos que van al registro 03 del archivo TXT.
 *
 * Las provincias no hacen falta pedirlas: están en CotProvincias. De paso este
 * comando deja states.cot_code listo para pre-cargar la provincia del COT desde
 * un domicilio ya cargado en la aplicación.
 */
class ImportCotCatalogs extends Command
{
    protected $signature = 'cot:import-catalogs
                            {tipo : provincias | nomenclador | unidades | mapeo}
                            {--file= : Ruta al archivo (SQL del nomenclador, CSV o JSON de unidades)}
                            {--dry-run : Informa qué haría sin escribir nada}';

    protected $description = 'Carga los catálogos de ARBA necesarios para emitir COT (provincias, nomenclador de productos y unidades de medida)';

    public function handle(): int
    {
        return match ($this->argument('tipo')) {
            'provincias' => $this->importarProvincias(),
            'nomenclador' => $this->importarNomenclador(),
            'unidades' => $this->importarUnidades(),
            'mapeo' => $this->importarMapeoUnidades(),
            default => $this->tipoInvalido(),
        };
    }

    /**
     * Provincias del COT, y de paso el enlace con la tabla states.
     *
     * states.cot_code existe para poder proponer la provincia del COT a partir
     * del domicilio que ya está cargado en la aplicación. Se deriva del sufijo
     * del código ISO 3166-2 (Buenos Aires = AR-B -> 'B'), que coincide con la
     * tabla de ARBA: verificado contra las 24 provincias.
     */
    private function importarProvincias(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun) {
            foreach (CotProvincias::PROVINCIAS as $codigo => $descripcion) {
                DB::table('cot_provincias')->updateOrInsert(
                    ['codigo' => $codigo],
                    ['descripcion' => $descripcion, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Provincias del COT: ' . count(CotProvincias::PROVINCIAS));

        return $this->enlazarProvinciasConStates($dryRun);
    }

    /**
     * Completa states.cot_code con la letra que usa ARBA en el COT.
     */
    private function enlazarProvinciasConStates(bool $dryRun): int
    {
        $states = DB::table('states')->select('id', 'name', 'codigo31662', 'cot_code')->get();

        if ($states->isEmpty()) {
            $this->warn('La tabla states está vacía: no hay a qué enlazar. Corré el seeder de estados y volvé a intentar.');

            return self::SUCCESS;
        }

        $actualizados = 0;
        $sinCodigo = 0;

        foreach ($states as $state) {
            $letra = $this->letraDesdeIso31662($state->codigo31662);

            if ($letra === null || ! CotProvincias::existe($letra)) {
                $sinCodigo++;
                $this->warn("Sin código COT: {$state->name} (codigo31662 = '{$state->codigo31662}')");

                continue;
            }

            if (! $dryRun) {
                DB::table('states')->where('id', $state->id)->update(['cot_code' => $letra]);
            }

            $actualizados++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "states enlazados con la provincia del COT: {$actualizados}");

        if ($sinCodigo > 0) {
            $this->warn("Estados sin código COT: {$sinCodigo}");
        }

        return self::SUCCESS;
    }

    /**
     * Nomenclador de productos de ARBA, desde su script SQL.
     */
    private function importarNomenclador(): int
    {
        $file = $this->option('file');

        if (! $file || ! is_file($file)) {
            $this->error('Pasá --file con la ruta al script del nomenclador (arba_nomenclador.sql).');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $filas = NomencladorSqlParser::filas((string) file_get_contents($file));

        if ($filas === []) {
            $this->error('No se pudo leer ninguna fila del script. ¿Es el archivo de ARBA?');

            return self::FAILURE;
        }

        $porTipo = array_count_values(array_column($filas, 'tipo'));

        foreach ($porTipo as $tipo => $cantidad) {
            $this->line("  {$tipo}: {$cantidad}");
        }

        $productos = array_filter($filas, fn (array $f) => $f['tipo'] === ArbaNomenclador::VARIEDAD);

        if ($productos === []) {
            $this->error('El script no trae variedades: sin ellas no hay códigos de producto de 6 dígitos.');

            return self::FAILURE;
        }

        $huerfanos = $this->huerfanos($filas);

        if ($huerfanos !== []) {
            $this->error('Hay ' . count($huerfanos) . " filas cuyo padre no existe en el archivo. Ejemplo: {$huerfanos[0]}");

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info('[dry-run] Se importarían ' . count($filas) . ' filas (' . count($productos) . ' productos).');

            return self::SUCCESS;
        }

        // La tabla tiene una FK auto-referencial: hay que borrar de hijo a padre
        // y después insertar de padre a hijo.
        $this->vaciarNomenclador();

        $insertadas = 0;

        foreach (array_chunk(NomencladorSqlParser::ordenarPadresPrimero($filas), 500) as $lote) {
            DB::table('arba_nomenclador_tb')->insert($lote);
            $insertadas += count($lote);
        }

        $this->info("Nomenclador importado: {$insertadas} filas ({$porTipo['VARIEDAD']} productos de 6 dígitos).");

        return self::SUCCESS;
    }

    /**
     * La FK obliga a borrar primero las hojas.
     *
     * El borrado se hace en statements separados por largo de código, de mayor a
     * menor: un hijo siempre tiene el código más largo que su padre. No alcanza
     * con ordenar la lista y borrar de a 500, porque dentro de un mismo DELETE
     * MySQL no garantiza en qué orden evalúa las filas y un capítulo puede irse
     * antes que sus rubros hijos (error 1451).
     */
    private function vaciarNomenclador(): void
    {
        $porLargo = [];

        foreach (DB::table('arba_nomenclador_tb')->pluck('codigo')->all() as $codigo) {
            $porLargo[strlen($codigo)][] = $codigo;
        }

        krsort($porLargo);

        foreach ($porLargo as $grupo) {
            DB::table('arba_nomenclador_tb')->whereIn('codigo', $grupo)->delete();
        }
    }

    /**
     * @param  array<int, array{codigo: string, codigo_padre: ?string}>  $filas
     * @return array<int, string>
     */
    private function huerfanos(array $filas): array
    {
        $codigos = array_flip(array_column($filas, 'codigo'));
        $huerfanos = [];

        foreach ($filas as $fila) {
            if ($fila['codigo_padre'] !== null && ! isset($codigos[$fila['codigo_padre']])) {
                $huerfanos[] = "{$fila['codigo']} -> {$fila['codigo_padre']}";
            }
        }

        return $huerfanos;
    }

    /**
     * Tabla de unidades de medida del registro 03 (un carácter por código).
     *
     * OJO: no confundir con la columna unidad_medida del nomenclador, que es
     * descriptiva ("KILOGRAMO(S)") y sirve para sugerir la unidad, pero NO es el
     * código de un carácter que pide el campo ARBA_CODIGO_UNIDAD_MEDIDA.
     */
    /**
     * Puente entre la unidad de medida del nomenclador y el código de UN
     * CARÁCTER que espera ARBA.
     *
     * Primero asegura las unidades base que referencia la clave foránea y
     * después carga el mapeo. Es idempotente: se puede correr las veces que
     * haga falta.
     */
    private function importarMapeoUnidades(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $mapeo = [
            'UNIDAD' => 'U',
            'KILOGRAMO' => 'K',
            'TONELADA' => 'T',
            'LITRO' => 'L',
            'METRO' => 'M',
            'METRO CUBICO' => 'C',
            'DOCENA' => 'D',
        ];

        foreach ($mapeo as $descripcion => $codigo) {
            if ($dryRun) {
                continue;
            }

            // La FK apunta a cot_unidades_medida: la unidad tiene que existir.
            CotUnidadMedida::updateOrCreate(
                ['codigo' => $codigo],
                ['descripcion' => ucfirst(mb_strtolower($descripcion)), 'active' => true]
            );

            CotMapeoUnidadNomenclador::updateOrCreate(
                ['descripcion_nomenclador' => $descripcion],
                ['codigo_unidad' => $codigo]
            );
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . 'Mapeo de unidades del nomenclador: ' . count($mapeo) . ' filas');

        return self::SUCCESS;
    }

    private function importarUnidades(): int
    {
        $file = $this->option('file');

        if (! $file || ! is_file($file)) {
            $this->error('Pasá --file con la ruta al CSV o JSON de unidades de medida.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $filas = $this->leerArchivo($file);

        $this->info("Filas leídas de {$file}: " . count($filas));

        $importados = 0;
        $rechazados = [];

        foreach ($filas as $indice => $fila) {
            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $descripcion = mb_substr(trim((string) ($fila['descripcion'] ?? '')), 0, 191);

            if (mb_strlen($codigo) !== 1) {
                $rechazados[] = 'fila ' . ($indice + 2) . ": código '{$codigo}' inválido (tiene que ser de un carácter)";

                continue;
            }

            if (! $dryRun) {
                CotUnidadMedida::updateOrCreate(
                    ['codigo' => mb_strtoupper($codigo)],
                    ['descripcion' => $descripcion, 'active' => true]
                );
            }

            $importados++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Unidades de medida importadas: {$importados}");

        if ($rechazados !== []) {
            $this->warn('Filas rechazadas: ' . count($rechazados));

            foreach (array_slice($rechazados, 0, 20) as $motivo) {
                $this->line("  {$motivo}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Acepta JSON o CSV. En CSV autodetecta el separador y saltea la cabecera si
     * la primera fila no tiene un código válido.
     *
     * @return array<int, array{codigo: mixed, descripcion: mixed}>
     */
    private function leerArchivo(string $path): array
    {
        $contenido = (string) file_get_contents($path);

        if (str_ends_with(strtolower($path), '.json')) {
            $json = json_decode($contenido, true);

            if (! is_array($json)) {
                throw new \RuntimeException('El JSON no se pudo interpretar como una lista.');
            }

            return $this->normalizarClaves($json);
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $separador = $this->detectarSeparador($lineas[0] ?? '');
        $filas = [];

        foreach ($lineas as $i => $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $celdas = str_getcsv($linea, $separador);

            if ($i === 0 && ! $this->pareceCodigo($celdas[0] ?? '')) {
                continue;
            }

            $filas[] = ['codigo' => $celdas[0] ?? '', 'descripcion' => $celdas[1] ?? ''];
        }

        return $filas;
    }

    /**
     * @return array<int, array{codigo: mixed, descripcion: mixed}>
     */
    private function normalizarClaves(array $json): array
    {
        $filas = [];

        foreach ($json as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $filas[] = [
                'codigo' => $fila['codigo'] ?? $fila['code'] ?? $fila[0] ?? '',
                'descripcion' => $fila['descripcion'] ?? $fila['description'] ?? $fila[1] ?? '',
            ];
        }

        return $filas;
    }

    private function detectarSeparador(string $primeraLinea): string
    {
        foreach ([';', ',', "\t", '|'] as $candidato) {
            if (substr_count($primeraLinea, $candidato) > 0) {
                return $candidato;
            }
        }

        return ',';
    }

    private function pareceCodigo(string $celda): bool
    {
        return trim($celda) !== '' && (bool) preg_match('/^[0-9A-Za-z]{1,6}$/', trim($celda));
    }

    private function letraDesdeIso31662(?string $iso): ?string
    {
        $iso = strtoupper(trim((string) $iso));

        if ($iso === '' || ! preg_match('/^[A-Z]{2}-([A-Z])$/', $iso, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function tipoInvalido(): int
    {
        $this->error('Tipo inválido. Usá: provincias, nomenclador o unidades.');

        return self::FAILURE;
    }
}
