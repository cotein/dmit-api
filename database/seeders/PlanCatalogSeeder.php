<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use App\Src\Services\Plans\PlanCatalogDefinition;
use Illuminate\Database\Seeder;

/**
 * Carga el catálogo de planes con sus topes y funciones.
 *
 * Es IDEMPOTENTE: se puede correr las veces que haga falta, también en producción
 * después de un deploy. Actualiza el nombre y el orden, y REEMPLAZA los topes y las
 * funciones del plan por los de la definición, para que un tope borrado a mano no
 * quede vivo por olvido.
 *
 * NO se llama desde DatabaseSeeder a propósito: ese seeder hace TRUNCATE de tablas
 * del sistema y no es algo que se quiera disparar junto con la carga del catálogo.
 * Se corre sólo:
 *
 *     php artisan db:seed --class=PlanCatalogSeeder
 *
 * ...o con el comando `php artisan plan:cargar-catalogo`, que además informa qué
 * quedó cargado.
 */
class PlanCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlanCatalogDefinition::plans() as $definicion) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $definicion['code']],
                [
                    'name' => $definicion['name'],
                    'description' => $definicion['description'],
                    'position' => $definicion['position'],
                    'is_internal' => $definicion['is_internal'],
                    'price_amount' => $definicion['price_amount'],
                    'price_currency' => 'ARS',
                    'is_active' => true,
                ]
            );

            $this->cargarTopes($plan, $definicion['limits']);
            $this->cargarFunciones($plan, $definicion['features']);
        }
    }

    /**
     * @param  array<string, int|null>  $limites
     */
    private function cargarTopes(Plan $plan, array $limites): void
    {
        foreach ($limites as $resource => $tope) {
            PlanLimit::query()->updateOrCreate(
                ['plan_id' => $plan->id, 'resource' => $resource],
                ['limit_value' => $tope]
            );
        }

        // Un recurso que ya no define este plan se borra: si quedara, el tope viejo
        // seguiría aplicándose y no habría forma de verlo desde la pantalla.
        PlanLimit::query()
            ->where('plan_id', $plan->id)
            ->whereNotIn('resource', array_keys($limites))
            ->delete();
    }

    /**
     * @param  array<int, string>  $funciones
     */
    private function cargarFunciones(Plan $plan, array $funciones): void
    {
        foreach ($funciones as $feature) {
            PlanFeature::query()->updateOrCreate(['plan_id' => $plan->id, 'feature' => $feature]);
        }

        PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->whereNotIn('feature', $funciones)
            ->delete();
    }
}
