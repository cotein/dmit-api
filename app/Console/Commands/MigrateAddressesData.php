<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAddressesData extends Command
{
    protected $signature = 'migrate:addresses';
    protected $description = 'Migrate addresses data from old database to new database using chunks';

    public function handle()
    {
        ini_set('memory_limit', '512M');
        // Definir el tamaño del chunk
        $chunkSize = 1000;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('addresses')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('addresses')
            ->orderBy('addressable_id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($addresses) {
                $this->info("Processing a chunk of " . count($addresses) . " records...");

                $dataToInsert = [];

                foreach ($addresses as $address) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'code' => $address->code,
                        'country_id' => $address->country_id,
                        'state_id' => $address->province_id,
                        'city' => $address->city,
                        'street' => $address->address,
                        'number' => (int)$address->number,
                        'cp' => $address->cp,
                        'obs' => $address->obs,
                        'geocoder' => json_encode($address->geocoder),
                        'addressable_id' => $address->addressable_id + 31592,
                        'addressable_type' => $address->addressable_type,
                        'type_id' => $address->type_id,
                        'active' => $address->status_id == 1 ? 1 : 0,
                        'between_streets' => $address->between_streets,
                        'created_at' => $address->created_at,
                        'updated_at' => $address->updated_at,
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('mysql')->table('addresses')->insert($dataToInsert);
                unset($dataToInsert);
                gc_collect_cycles();
            });

        $this->info('Addresses data migrated successfully.');
    }
}
