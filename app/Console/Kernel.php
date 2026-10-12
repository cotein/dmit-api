<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Red de seguridad de Mercado Pago: los webhooks se pierden, así que cada
        // 10 minutos se reconsultan los cobros pendientes y los eventos fallidos.
        // Requiere que el scheduler esté corriendo (cron con schedule:run).
        $schedule->command('mercadopago:conciliar')->everyTenMinutes()->withoutOverlapping();

        // MercadoLibre: los webhooks se pierden y las publicaciones cambian sin
        // aviso. Cada 10 minutos se procesan las notificaciones pendientes, se
        // importan publicaciones y ventas, y se empuja precio y stock.
        // Requiere el mismo cron con schedule:run que Mercado Pago.
        $schedule->command('mercadolibre:sincronizar')->everyTenMinutes()->withoutOverlapping();

        // Planes: el lugar del contador que se toma antes de llamar al organismo
        // vence solo si el comprobante no llegó a confirmarse (se cortó el proceso,
        // se cayó la conexión). Sin este saneamiento ese lugar quedaría tomado para
        // siempre y el contribuyente perdería una factura del mes sin haberla
        // emitido. Es liviano: sólo toca reservas ya vencidas.
        $schedule->command('plan:sanear-reservas')->everyFiveMinutes()->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
