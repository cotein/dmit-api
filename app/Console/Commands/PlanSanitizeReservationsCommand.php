<?php

namespace App\Console\Commands;

use App\Src\Services\Plans\PlanUsage;
use Illuminate\Console\Command;

/**
 * Libera las reservas de consumo vencidas.
 *
 * POR QUÉ EXISTE: cuando se emite, el lugar del contador se toma ANTES de llamar al
 * organismo y se confirma recién cuando el comprobante existe. Si el proceso se corta
 * en el medio (se cayó el servidor, se fue la conexión), esa reserva quedaría tomando
 * un lugar para siempre y el contribuyente perdería una factura del mes sin haberla
 * emitido.
 *
 * Corre SOLO cada pocos minutos (ver app/Console/Kernel.php). Requiere que el
 * scheduler esté andando (`php artisan schedule:run` desde cron).
 */
class PlanSanitizeReservationsCommand extends Command
{
    protected $signature = 'plan:sanear-reservas';

    protected $description = 'Libera las reservas de consumo del plan que vencieron sin confirmarse';

    public function handle(PlanUsage $usage): int
    {
        $liberadas = $usage->sanitizeExpired();

        $this->info('Reservas vencidas liberadas: ' . $liberadas);

        return self::SUCCESS;
    }
}
