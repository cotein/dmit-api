<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Src\Services\Plans\PlanSwitches;
use Illuminate\Console\Command;

/**
 * Prende o apaga el corte por tope de plan.
 *
 * ES LA PALANCA DE LA PUESTA EN MARCHA y también la vuelta atrás: apagar el general
 * deja a todos facturando como antes en un minuto.
 *
 * ORDEN OBLIGATORIO (el que evita el riesgo grave del deploy): primero se cargan los
 * planes, después se asigna el plan sin topes a TODOS los CUIT existentes, y recién
 * entonces se enciende. Si se encendiera antes, los clientes actuales quedarían
 * limitados a diez facturas.
 *
 * Ejemplos:
 *   php artisan plan:interruptor --estado                 (qué dice hoy)
 *   php artisan plan:interruptor --encender               (enciende para todos)
 *   php artisan plan:interruptor --apagar                 (vuelta atrás)
 *   php artisan plan:interruptor --cuit=20111111112 --encender   (sólo ese CUIT)
 *   php artisan plan:interruptor --cuit=20111111112 --heredar    (vuelve al general)
 */
class PlanGatingSwitchCommand extends Command
{
    protected $signature = 'plan:interruptor
        {--encender : Enciende el corte}
        {--apagar : Apaga el corte}
        {--heredar : Borra la excepción del CUIT y vuelve a heredar del interruptor general}
        {--cuit= : CUIT (afip_number) de la empresa, para tocar sólo esa}';

    protected $description = 'Prende o apaga el corte por tope de plan (general o para un CUIT puntual)';

    public function handle(PlanSwitches $switches): int
    {
        $cuit = $this->option('cuit');
        $companyId = null;

        if ($cuit !== null) {
            $companyId = Company::query()->where('afip_number', $cuit)->value('id');

            if ($companyId === null) {
                $this->error('No hay ninguna empresa con el CUIT ' . $cuit . '.');

                return self::FAILURE;
            }

            $companyId = (int) $companyId;
        }

        $quiereEncender = (bool) $this->option('encender');
        $quiereApagar = (bool) $this->option('apagar');
        $quiereHeredar = (bool) $this->option('heredar');

        if (! $quiereEncender && ! $quiereApagar && ! $quiereHeredar) {
            $this->mostrarEstado($switches);

            return self::SUCCESS;
        }

        if (($quiereEncender ? 1 : 0) + ($quiereApagar ? 1 : 0) + ($quiereHeredar ? 1 : 0) > 1) {
            $this->error('Elegí una sola acción: --encender, --apagar o --heredar.');

            return self::FAILURE;
        }

        if ($companyId === null) {
            if ($quiereHeredar) {
                $this->error('--heredar sólo tiene sentido con --cuit.');

                return self::FAILURE;
            }

            $switches->setGlobally($quiereEncender);

            $this->info($quiereEncender
                ? 'Corte ENCENDIDO para todos los contribuyentes.'
                : 'Corte APAGADO para todos: todos facturan como antes.');

            $this->mostrarEstado($switches);

            return self::SUCCESS;
        }

        if ($quiereHeredar) {
            $switches->setForCompany($companyId, null);
            $this->info('El CUIT ' . $cuit . ' vuelve a heredar del interruptor general.');
        } else {
            $switches->setForCompany($companyId, $quiereEncender);
            $this->info('Corte ' . ($quiereEncender ? 'ENCENDIDO' : 'APAGADO') . ' para el CUIT ' . $cuit . '.');
        }

        $this->mostrarEstado($switches);

        return self::SUCCESS;
    }

    private function mostrarEstado(PlanSwitches $switches): void
    {
        $estado = $switches->state();

        $this->line('');
        $this->line('Interruptor general: ' . ($estado['global'] ? 'ENCENDIDO' : 'APAGADO'));

        if ($estado['companies'] === []) {
            $this->line('Excepciones por CUIT: ninguna.');

            return;
        }

        $this->line('Excepciones por CUIT:');

        foreach ($estado['companies'] as $companyId => $encendido) {
            $this->line('  empresa ' . $companyId . ': ' . ($encendido ? 'ENCENDIDO' : 'APAGADO'));
        }
    }
}
