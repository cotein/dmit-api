<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePriceListsData extends Command
{
    protected $signature = 'migrate:price-lists {company_id : The company ID} {user_id : The user ID}';
    protected $description = 'Migrate price lists data from old database to new database using chunks';

    public function handle()
    {
        // Obtener los parámetros company_id y user_id
        $companyId = $this->argument('company_id');
        $userId = $this->argument('user_id');

        // Validar que los parámetros sean válidos
        if (!is_numeric($companyId)) {
            $this->error('Company ID must be a number.');
            return;
        }

        if (!is_numeric($userId)) {
            $this->error('User ID must be a number.');
            return;
        }

        // Definir el tamaño del chunk
        $chunkSize = 250; // Puedes ajustar este valor según tus necesidades

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('price_list')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('price_list')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($priceLists) use ($companyId, $userId) {
                $this->info("Processing a chunk of " . count($priceLists) . " records...");

                $dataToInsert = [];

                foreach ($priceLists as $priceList) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'name' => $priceList->name,
                        'active' => $priceList->enable, // Cambio de nombre
                        'profit_percentage' => $priceList->benefit, // Cambio de nombre
                        'user_id' => $userId, // Asignar el user_id pasado como parámetro
                        'company_id' => $companyId, // Asignar el company_id pasado como parámetro
                        'created_at' => $priceList->created_at,
                        'updated_at' => $priceList->updated_at,
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('mysql')->table('price_lists')->insert($dataToInsert);
            });

        $this->info('Price lists data migrated successfully.');
    }
}
