<?php

namespace App\Console\Commands;

use App\Src\Services\Plans\PlanChanger;
use Illuminate\Console\Command;

/**
 * Asigna el plan interno SIN TOPES a todas las empresas que no tengan suscripción.
 *
 * ES EL PASO QUE EVITA EL RIESGO GRAVE DEL DEPLOY: si el corte se encendiera antes
 * de esta asignación, todos los clientes actuales —que nunca eligieron un plan—
 * quedarían en el gratuito y se les cortaría la emisión a las diez facturas.
 *
 * El comando no encenderá nada: sólo carga los planes que faltan y avisa si quedó
 * alguna empresa sin asignar. El encendido es un paso aparte, con el interruptor.
 *
 * Ejemplos:
 *   php artisan plan:asignar-interno --simular   (qué haría, sin escribir nada)
 *   php artisan plan:asignar-interno             (lo hace y verifica que dé cero)
 */
class PlanAssignInternalCommand extends Command
{
    protected $signature = 'plan:asignar-interno
        {--simular : Muestra qué empresas se asignarían, sin tocar la base}';

    protected $description = 'Asigna el plan interno sin topes a las empresas que todavía no tienen plan';

    public function handle(PlanChanger $changer): int
    {
        $pendientes = $changer->companiesWithoutPlan();

        if ($pendientes === []) {
            $this->info('Todas las empresas ya tienen plan asignado.');

            return self::SUCCESS;
        }

        $this->line('Empresas sin plan: ' . count($pendientes));

        if ($this->option('simular')) {
            $this->line('Simulación: no se escribió nada. Se asignarían: ' . implode(', ', $pendientes));
            $this->line('');
            $this->line('Recordá: NO encender el corte antes de correr esto de verdad.');

            return self::SUCCESS;
        }

        foreach ($pendientes as $companyId) {
            $changer->assignInternal((int) $companyId);
        }

        $restantes = $changer->companiesWithoutPlan();

        $this->info('Plan interno asignado a ' . count($pendientes) . ' empresa(s).');

        // El control que decide si se puede encender: si no da cero, no se sigue.
        if ($restantes !== []) {
            $this->error('Quedaron ' . count($restantes) . ' empresas sin plan: NO encender el corte todavía.');

            return self::FAILURE;
        }

        $this->info('Control: cero empresas sin suscripción. Recién ahora se puede encender el corte.');

        return self::SUCCESS;
    }
}
