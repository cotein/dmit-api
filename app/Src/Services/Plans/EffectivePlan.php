<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\Plan;
use Illuminate\Contracts\Support\Arrayable;

/**
 * El plan que RIGE para un CUIT hoy, ya resuelto.
 *
 * Es un valor plano (no un modelo) a propósito: se cachea por unos segundos y se
 * comparte entre el gating y la pantalla, y un modelo cacheado obligaría a pensar
 * si sus relaciones siguen frescas cada vez que se lee un tope.
 *
 * Este objeto NO responde "¿puede emitir?": eso es del gating. Acá sólo se dice qué
 * plan rige, con qué topes y por qué.
 *
 * @implements Arrayable<string, mixed>
 */
final class EffectivePlan implements Arrayable
{
    /** Hay una suscripción vigente: rige el plan asignado. */
    public const STATE_ACTIVE = 'active';

    /** Rige el plan gratuito, sea por degradación o porque no hay suscripción. */
    public const STATE_FREE = 'free';

    /**
     * @param  array<string, int|null>  $limits    resource => tope (null = sin tope)
     * @param  array<string, bool>      $features  feature => habilitada
     */
    public function __construct(
        public readonly ?int $planId,
        public readonly string $code,
        public readonly string $name,
        public readonly bool $isInternal,
        public readonly string $state,
        public readonly ?string $reason,
        public readonly ?string $since,
        public readonly array $limits,
        public readonly array $features,
        public readonly ?int $requestedPlanId,
    ) {
    }

    /**
     * El tope del recurso, o null cuando no hay tope.
     */
    public function limitFor(string $resource): ?int
    {
        $limit = $this->limits[$resource] ?? null;

        return $limit === null ? null : (int) $limit;
    }

    public function hasFeature(string $feature): bool
    {
        return ($this->features[$feature] ?? false) === true;
    }

    /**
     * Un plan sin tope para el recurso: es lo que la pantalla muestra como
     * "sin límite", nunca un número.
     */
    public function isUnlimited(string $resource): bool
    {
        return $this->limitFor($resource) === null;
    }

    public function isFree(): bool
    {
        return $this->code === Plan::CODE_FREE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan' => [
                'code' => $this->code,
                'name' => $this->name,
                'is_internal' => $this->isInternal,
            ],
            'state' => [
                'code' => $this->state,
                'reason' => $this->reason,
                'since' => $this->since,
            ],
            'requested_plan_id' => $this->requestedPlanId,
        ];
    }

    /**
     * Forma plana para guardar en caché por unos segundos.
     *
     * Se cachea este array y no el objeto: así lo que se guarda es exactamente lo
     * que se vuelve a leer, sin depender de cómo serialice el driver de turno.
     *
     * @return array<string, mixed>
     */
    public function toCacheArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'code' => $this->code,
            'name' => $this->name,
            'is_internal' => $this->isInternal,
            'state' => $this->state,
            'reason' => $this->reason,
            'since' => $this->since,
            'limits' => $this->limits,
            'features' => $this->features,
            'requested_plan_id' => $this->requestedPlanId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromCacheArray(array $data): self
    {
        return new self(
            planId: $data['plan_id'] === null ? null : (int) $data['plan_id'],
            code: (string) $data['code'],
            name: (string) $data['name'],
            isInternal: (bool) $data['is_internal'],
            state: (string) $data['state'],
            reason: $data['reason'] === null ? null : (string) $data['reason'],
            since: $data['since'] === null ? null : (string) $data['since'],
            limits: (array) $data['limits'],
            features: (array) $data['features'],
            requestedPlanId: $data['requested_plan_id'] === null ? null : (int) $data['requested_plan_id'],
        );
    }
}
