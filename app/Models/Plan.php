<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un nivel del producto (Gratis, Emprendedor, Profesional, Estudio) o el plan
 * interno de los CUIT heredados.
 *
 * Es de plataforma: no pertenece a ninguna empresa y por eso NO lleva
 * BelongsToCompanyTrait. Con CompanyScope encima, un usuario sin empresa no podría
 * ni listar los planes, y el mismo catálogo devolvería cero filas según quién
 * pregunte — que es exactamente el tipo de bug que no queremos en algo que decide
 * si alguien puede facturar.
 */
class Plan extends Model
{
    /** Plan interno: sin topes, para los CUIT que ya existían. No se vende. */
    public const CODE_INTERNAL = 'internal';

    /** Plan gratuito: es el que rige cuando no hay suscripción o no está vigente. */
    public const CODE_FREE = 'free';

    protected $fillable = [
        'code',
        'name',
        'description',
        'position',
        'is_internal',
        'price_amount',
        'price_currency',
        'is_active',
    ];

    protected $casts = [
        'position' => 'integer',
        'is_internal' => 'boolean',
        'price_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /**
     * El tope de un recurso, o null cuando no hay tope.
     *
     * Se lee de la colección ya cargada cuando está disponible para no ir a la base
     * una vez por recurso en la misma pantalla.
     */
    public function limitFor(string $resource): ?int
    {
        $limit = $this->relationLoaded('limits')
            ? $this->limits->firstWhere('resource', $resource)
            : $this->limits()->where('resource', $resource)->first();

        if ($limit === null || $limit->limit_value === null) {
            return null;
        }

        return (int) $limit->limit_value;
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->relationLoaded('features')) {
            return $this->features->contains('feature', $feature);
        }

        return $this->features()->where('feature', $feature)->exists();
    }
}
