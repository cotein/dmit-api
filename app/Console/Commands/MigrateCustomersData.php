<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCustomersData extends Command
{
    protected $signature = 'migrate:customers';
    protected $description = 'Migrate customers data from old database to new database using chunks';

    public function handle()
    {
        // Obtener el máximo ID de la tabla nueva
        $maxId = DB::connection('mysql')->table('customers')->max('id');
        $idOffset = $maxId + 1; // Sumar 1 para evitar conflictos
        // Definir el tamaño del chunk
        $chunkSize = 1000; // Puedes ajustar este valor según tus necesidades

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('customers')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('customers')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($customers) use ($idOffset) {
                $this->info("Processing a chunk of " . count($customers) . " records...");

                $dataToInsert = [];

                foreach ($customers as $customer) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'id' => $customer->id + $idOffset,
                        'name' => $customer->name,
                        'last_name' => null, // No existe en la tabla antigua
                        'fantasy_name' => null, // No existe en la tabla antigua
                        'dni' => $customer->number, // Cambio de nombre
                        'afip_number' => $customer->number, // Cambio de nombre
                        'afip_inscription_id' => $customer->inscription_id, // Cambio de nombre
                        'afip_document_id' => $customer->purchaser_document_id, // Cambio de nombre
                        'afip_type' => null, // No existe en la tabla antigua
                        'contact' => $customer->contact,
                        'afip_data' => json_encode($customer->afip_data), // Cambio de tipo (json a text)
                        'created_at' => $customer->created_at,
                        'updated_at' => $customer->updated_at,
                        'cell_phone' => $customer->cell_phone,
                        'phone_1' => $customer->phone_1,
                        'phone_2' => $customer->phone_2,
                        'phone_3' => $customer->phone_3,
                        'email' => $customer->email,
                        'obs' => json_encode($customer->others), // Cambio de nombre y tipo
                        'active' => $customer->status_id == 1 ? 1 : 0, // Cambio de nombre y lógica
                        'meli_id' => $customer->meli_id,
                        'meli_nick' => $customer->meli_nick,
                        'pay_condition' => $customer->pay_condition,
                        'customer_type_id' => $customer->customer_type_id,
                        'company_id' => 5, // No existe en la tabla antigua
                        'user_id' => 5, // No existe en la tabla antigua
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('mysql')->table('customers')->insert($dataToInsert);
                unset($dataToInsert);
            });

        $this->info('Customers data migrated successfully.');
    }
}
