<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Src\Services\Plans\PlanCatalog;
use App\Src\Services\Plans\PlanPeriod;
use App\Src\Services\Plans\PlanUsage;
use Illuminate\Console\Command;

/**
 * Recálculo del contador contra los comprobantes del periodo.
 *
 * INFORMA, NO CORRIGE. Es la red de seguridad que dice si el contador quedó
 * desfasado de lo que realmente se emitió. Corregir en silencio taparía el bug que
 * produjo la diferencia, que es exactamente lo que se quiere ver: por eso el comando
 * avisa y deja la decisión en manos de una persona. (El caso típico de diferencia
 * legítima: operaciones cargadas por fuera del sistema, o comprobantes de un mes
 * anterior registrados con fecha nueva.)
 *
 * Corre A MANO desde la configuración, no está en el scheduler.
 *
 * Ejemplos:
 *   php artisan plan:recalcular
 *   php artisan plan:recalcular --periodo=2026-11
 *   php artisan plan:recalcular --cuit=20111111112
 */
class PlanRecalculateCommand extends Command
{
    protected $signature = 'plan:recalcular
        {--periodo= : Mes a revisar en formato AAAA-MM (por defecto, el actual)}
        {--cuit= : Revisar sólo la empresa de ese CUIT}';

    protected $description = 'Compara el contador del plan con los comprobantes del periodo e informa las diferencias';

    public function handle(PlanUsage $usage): int
    {
        $periodo = $this->option('periodo');
        $period = $periodo !== null ? PlanPeriod::fromKey($periodo) : PlanPeriod::current();

        $cuit = $this->option('cuit');
        $empresas = [];

        if ($cuit !== null) {
            $id = Company::query()->where('afip_number', $cuit)->value('id');

            if ($id === null) {
                $this->error('No hay ninguna empresa con el CUIT ' . $cuit . '.');

                return self::FAILURE;
            }

            $empresas = [(int) $id];
        } else {
            $empresas = Company::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        $this->line('Periodo: ' . $period->key . ' · empresas revisadas: ' . count($empresas));

        $filas = [];

        foreach ($empresas as $companyId) {
            foreach (PlanCatalog::countedResources() as $resource) {
                $r = $usage->recount($companyId, $resource, $period);

                if ($r['difference'] !== 0) {
                    $filas[] = ['empresa' => $companyId, 'recurso' => $r['resource'], 'contador' => $r['counter'], 'comprobantes' => $r['documents'], 'diferencia' => $r['difference']];
                }
            }
        }

        if ($filas === []) {
            $this->info('El contador coincide con los comprobantes del periodo.');

            return self::SUCCESS;
        }

        $this->warn('Hay diferencias entre el contador y los comprobantes. NO se corrigieron.');
        $this->table(['empresa', 'recurso', 'contador', 'comprobantes', 'diferencia'], $filas);

        return self::SUCCESS;
    }
}
