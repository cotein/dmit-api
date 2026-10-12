<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * El catálogo de planes y sus topes, leído de la base.
 *
 * Los nombres de los RECURSOS viven acá y en un solo lugar: son los mismos que
 * viajan en el contrato del rechazo y en la pantalla, así que renombrarlos en un
 * lado y no en el otro rompería el aviso sin que ningún test se entere.
 */
final class PlanCatalog
{
    /** Facturas y notas: es el recurso que se cuenta al emitir comprobantes. */
    public const RESOURCE_INVOICES = 'invoices';

    /** Remitos: contador independiente, con su propio tope y su propio texto. */
    public const RESOURCE_REMITOS = 'remitos';

    /**
     * Los recursos que este corte cuenta de verdad.
     *
     * Usuarios y puntos de venta tienen tope declarado en el catálogo, pero todavía
     * no se cuentan: se agregan acá el día que se enforcen, no antes (si estuvieran
     * en esta lista sin enganche, el contrato prometería un corte que no existe).
     */
    public static function countedResources(): array
    {
        return [
            self::RESOURCE_INVOICES,
            self::RESOURCE_REMITOS,
        ];
    }

    /**
     * Todos los planes visibles, en orden, con topes y funciones ya cargados.
     *
     * El plan interno NO entra: no se vende y no se muestra.
     */
    public function sellable(): Collection
    {
        return Plan::query()
            ->where('is_internal', false)
            ->where('is_active', true)
            ->with(['limits', 'features'])
            ->orderBy('position')
            ->get();
    }

    public function findByCode(string $code): ?Plan
    {
        return Plan::query()->where('code', $code)->first();
    }

    public function findById(int $id): ?Plan
    {
        return Plan::query()->find($id);
    }

    /**
     * El plan que rige cuando no hay suscripción o no está vigente.
     *
     * Si el gratuito no estuviera cargado, hay un error de datos y NO se inventa un
     * plan: quien llame tiene que poder verlo. Devolver null en silencio haría que el
     * gating tratara a todos como "sin tope", que es el peor default posible.
     */
    public function free(): ?Plan
    {
        return $this->findByCode(Plan::CODE_FREE);
    }

    /**
     * El plan interno sin topes, que es el que se le asigna a los CUIT que ya
     * existían para que nadie se despierte bloqueado.
     */
    public function internal(): ?Plan
    {
        return $this->findByCode(Plan::CODE_INTERNAL);
    }

    /**
     * El tope de un recurso para un plan: null es SIN TOPE.
     */
    public function limitFor(Plan $plan, string $resource): ?int
    {
        return $plan->limitFor($resource);
    }
}
