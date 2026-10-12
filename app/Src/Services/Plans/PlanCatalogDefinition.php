<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\Plan;
use App\Models\PlanFeature;

/**
 * El catálogo de planes, escrito UNA sola vez.
 *
 * POR QUÉ ES UN ARRAY Y NO DOS LUGARES: los planes tienen que quedar cargados igual
 * en el servidor (por `php artisan db:seed`) y en la base local de desarrollo (por
 * el runner PDO, porque en esta máquina artisan no corre). Si cada camino tuviera su
 * propia copia, un día un tope cambiaría en uno y no en el otro, y el gating
 * cortaría distinto según el entorno.
 *
 * TOPES: null es SIN TOPE. El plan interno no lleva ningún renglón de tope y tampoco
 * límites: es el que se les asigna a los contribuyentes que ya existían.
 *
 * PRECIOS: en este corte el cobro está SIMULADO y la lista de precios todavía no está
 * definida, así que los importes quedan en cero y cada plan se carga con la moneda
 * del sistema. Cuando se defina la lista, se cambia acá y se vuelve a correr la
 * carga: la carga es idempotente.
 */
final class PlanCatalogDefinition
{
    /**
     * Los recursos con tope. 'users' todavía no se cuenta pero se declara porque es
     * parte de lo que el cliente contrata y la pantalla lo muestra.
     *
     * @return array<string, int|null>
     */
    private static function limitos(?int $invoices, ?int $remitos, ?int $users): array
    {
        return [
            PlanCatalog::RESOURCE_INVOICES => $invoices,
            PlanCatalog::RESOURCE_REMITOS => $remitos,
            'users' => $users,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function plans(): array
    {
        return [
            [
                'code' => Plan::CODE_FREE,
                'name' => 'Gratis',
                'description' => 'Para probar el sistema facturando de verdad, sin costo.',
                'position' => 1,
                'is_internal' => false,
                'price_amount' => 0,
                'limits' => self::limitos(10, 10, 1),
                'features' => [],
            ],
            [
                'code' => 'emprendedor',
                'name' => 'Emprendedor',
                'description' => 'Para el comercio que factura todos los días.',
                'position' => 2,
                'is_internal' => false,
                'price_amount' => 0,
                'limits' => self::limitos(100, 100, 2),
                'features' => [
                    PlanFeature::FEATURE_LOGO,
                    PlanFeature::FEATURE_MERCADOPAGO,
                ],
            ],
            [
                'code' => 'profesional',
                'name' => 'Profesional',
                'description' => 'Para el negocio con más de un punto de venta y ventas online.',
                'position' => 3,
                'is_internal' => false,
                'price_amount' => 0,
                'limits' => self::limitos(500, 500, 5),
                'features' => [
                    PlanFeature::FEATURE_LOGO,
                    PlanFeature::FEATURE_WATERMARK,
                    PlanFeature::FEATURE_MERCADOPAGO,
                    PlanFeature::FEATURE_MERCADOLIBRE,
                ],
            ],
            [
                'code' => 'estudio',
                'name' => 'Estudio',
                'description' => 'Para el contador que administra varios contribuyentes.',
                'position' => 4,
                'is_internal' => false,
                'price_amount' => 0,
                'limits' => self::limitos(2000, null, null),
                'features' => [
                    PlanFeature::FEATURE_LOGO,
                    PlanFeature::FEATURE_WATERMARK,
                    PlanFeature::FEATURE_MERCADOPAGO,
                    PlanFeature::FEATURE_MERCADOLIBRE,
                    PlanFeature::FEATURE_API,
                ],
            ],
            [
                // El plan de los contribuyentes que ya estaban facturando: sin topes
                // y con todas las funciones, para que encender el corte no les quite
                // nada. No se vende y no se muestra en la pantalla de planes.
                'code' => Plan::CODE_INTERNAL,
                'name' => 'Plan interno (sin tope)',
                'description' => 'Contribuyentes que ya facturaban cuando se activó el control de topes.',
                'position' => 99,
                'is_internal' => true,
                'price_amount' => 0,
                'limits' => self::limitos(null, null, null),
                'features' => [
                    PlanFeature::FEATURE_LOGO,
                    PlanFeature::FEATURE_WATERMARK,
                    PlanFeature::FEATURE_MERCADOPAGO,
                    PlanFeature::FEATURE_MERCADOLIBRE,
                    PlanFeature::FEATURE_API,
                ],
            ],
        ];
    }
}
