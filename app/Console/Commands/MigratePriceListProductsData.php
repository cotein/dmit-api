<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePriceListProductsData extends Command
{
    protected $signature = 'migrate:pricelist-products';
    protected $description = 'Migrate pricelist products data from old database to new database using chunks';

    public function handle()
    {
        // Definir el tamaño del chunk
        $chunkSize = 1000;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('pricelist_products')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('pricelist_products')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($pricelistProducts) {
                $this->info("Processing a chunk of " . count($pricelistProducts) . " records...");

                $dataToInsert = [];

                foreach ($pricelistProducts as $pricelistProduct) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'pricelist_id' => $pricelistProduct->pricelist_id + 4,
                        'product_id' => $pricelistProduct->product_id + 5,
                        'price' => $pricelistProduct->price,
                        'profit_percentage' => 0.00, // Valor por defecto
                        'profit_rate' => 0.00, // Valor por defecto
                        'created_at' => $pricelistProduct->created_at,
                        'updated_at' => $pricelistProduct->updated_at,
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('dmit')->table('price_list_product')->insert($dataToInsert);
            });

        $this->info('Pricelist products data migrated successfully.');
    }
}
