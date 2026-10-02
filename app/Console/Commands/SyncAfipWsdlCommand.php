<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Src\Afip\AfipWsdl;
use Illuminate\Console\Command;

/**
 * Copia los WSDL oficiales de ARCA (resources/afip/wsdl) sobre los del paquete cotein/api-afip.
 *
 * Sin esto, en PRODUCCIÓN el SoapClient descarta <CondicionIVAReceptorId> y ARCA rechaza la
 * factura con 10245 (RG 5616). Correr después de cada composer install/update:
 *   php artisan afip:sync-wsdl
 */
class SyncAfipWsdlCommand extends Command
{
    protected $signature = 'afip:sync-wsdl {--force : Copia los WSDL aunque el del paquete ya esté actualizado}';

    protected $description = 'Actualiza los WSDL de ARCA que usa el paquete cotein/api-afip (RG 5616)';

    public function handle(): int
    {
        $informe = AfipWsdl::sync(AfipWsdl::ENVIRONMENTS, (bool) $this->option('force'));

        $this->table(
            ['Entorno', 'Estado', 'Detalle'],
            array_map(
                static fn (array $fila): array => [$fila['environment'], $fila['status'], $fila['detail']],
                $informe,
            ),
        );

        $errores = array_filter($informe, static fn (array $fila): bool => $fila['status'] === 'error');

        if ($errores !== []) {
            $this->error('No se pudieron actualizar todos los WSDL. Revisá los permisos de vendor/cotein/api-afip.');

            return self::FAILURE;
        }

        if ($this->output->isVerbose()) {
            foreach ($informe as $fila) {
                $this->line($fila['environment'] . ': ' . $fila['target']);
            }
        }

        return self::SUCCESS;
    }
}
