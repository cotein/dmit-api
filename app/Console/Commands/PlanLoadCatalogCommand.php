<?php

namespace App\Console\Commands;

use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Console\Command;

/**
 * Carga (o recarga) el catálogo de planes con sus topes y funciones.
 *
 * Es idempotente: correrlo dos veces deja lo mismo. Se puede usar en un deploy sin
 * miedo, y es la forma de cargar los planes cuando no se quiere correr el seeder
 * completo.
 */
class PlanLoadCatalogCommand extends Command
{
    protected $signature = 'plan:cargar-catalogo';

    protected $description = 'Carga el catálogo de planes con sus topes y funciones (idempotente)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => PlanCatalogSeeder::class, '--force' => true]);

        $planes = \App\Models\Plan::query()->orderBy('position')->get(['code', 'name', 'is_internal']);

        $this->line('');
        $this->info('Planes cargados: ' . $planes->count());

        foreach ($planes as $plan) {
            $this->line('  ' . $plan->code . ' — ' . $plan->name . ($plan->is_internal ? ' (interno, sin tope)' : ''));
        }

        return self::SUCCESS;
    }
}
