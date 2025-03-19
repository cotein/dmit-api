<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateProductsData extends Command
{
    protected $signature = 'migrate:products';
    protected $description = 'Migrate products data from old database to new database using chunks';

    public function handle()
    {
        // Definir el tamaño del chunk
        $chunkSize = 1000;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('products')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('products')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($products) {
                $this->info("Processing a chunk of " . count($products) . " records...");

                $dataToInsert = [];

                foreach ($products as $product) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'meli_id' => $product->meli_id,
                        'company_id' => 5, // Asignar company_id manualmente
                        'name' => $product->name,
                        'code' => $product->code,
                        'sub_title' => $product->sub_title,
                        'description' => $product->description,
                        'iva_id' => 3, //21% de IVA
                        'money_id' => 1, // Extraer money_id del JSON
                        'priority_id' => $product->priority_id,
                        'published_meli' => $product->published_meli,
                        'published_here' => $product->published_here,
                        'active' => $product->active,
                        'slug' => $product->slug,
                        'deleted_at' => $product->deleted_at,
                        'created_at' => $product->created_at,
                        'updated_at' => $product->updated_at,
                        'critical_stock' => $product->critical_stock,
                        'sale_by_meters' => $product->mts_by_unity ? 1 : 0, // Convertir mts_by_unity a sale_by_meters
                        'mts_by_unity' => $product->mts_by_unity,
                        'apply_discount' => $product->discount_percentage > 0 ? 1 : 0, // Convertir discount_percentage a apply_discount
                        'apply_discount_amount' => 0.00, // Valor por defecto
                        'apply_discount_percentage' => $product->discount_percentage,
                        'see_price_on_the_web' => 0, // Valor por defecto
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('dmit')->table('products')->insert($dataToInsert);
            });

        $this->info('Products data migrated successfully.');
    }
}
