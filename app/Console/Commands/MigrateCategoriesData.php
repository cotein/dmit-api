<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateCategoriesData extends Command
{
    protected $signature = 'migrate:categories';
    protected $description = 'Migrate categories data from old database to new database using chunks';

    public function handle()
    {
        // Definir el tamaño del chunk
        $chunkSize = 1000;

        // Obtener el total de registros para mostrar el progreso
        $totalRecords = DB::connection('vete')->table('categories')->count();
        $this->info("Total records to migrate: {$totalRecords}");

        // Procesar los registros en chunks
        DB::connection('vete')
            ->table('categories')
            ->orderBy('id') // Ordenar por ID para evitar problemas con chunks
            ->chunk($chunkSize, function ($categories) {
                $this->info("Processing a chunk of " . count($categories) . " records...");

                $dataToInsert = [];

                foreach ($categories as $category) {
                    // Mapear los campos de la base de datos antigua a la nueva
                    $dataToInsert[] = [
                        'code' => $category->code,
                        'parent_id' => $category->parent_id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'deleted_at' => $category->deleted_at,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                        'attributes' => $category->attributes,
                        'active' => $category->active,
                        'company_id' => 5, // Asignar company_id manualmente
                        'user_id' => 5, // Asignar user_id manualmente
                    ];
                }

                // Insertar el chunk en la nueva base de datos
                DB::connection('dmit')->table('categories')->insert($dataToInsert);
            });

        $this->info('Categories data migrated successfully.');
    }
}
