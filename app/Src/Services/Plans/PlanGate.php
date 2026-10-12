<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Exceptions\Plans\PlanDuplicateEmissionException;
use App\Exceptions\Plans\PlanLimitReachedException;
use App\Exceptions\Plans\PlanUsageUnavailableException;
use App\Models\PlanUsageReservation;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * La puerta: decide si una emisión puede entrar y lleva la cuenta.
 *
 * ES EL ÚNICO LUGAR QUE RESUELVE "¿puede emitir?". Ni el frontend ni otras partes
 * del backend lo deciden por su cuenta: el contador de la pantalla es informativo y
 * quien autoriza es esto, con el contador real.
 *
 * ORDEN DE UN PEDIDO DE EMISIÓN:
 *   1. si el interruptor de este CUIT está apagado, no hay corte: se sigue como
 *      antes de que existiera esta puerta;
 *   2. se resuelve el plan efectivo y su tope: sin tope, no hay nada que cortar;
 *   3. se toma el lugar (reserva) ANTES de llamar al organismo, con el contador
 *      bloqueado un instante para que dos pedidos simultáneos no entren los dos;
 *   4. si el organismo autoriza, se confirma el consumo; si rechaza, se libera el
 *      lugar y no se consume nada.
 *
 * LÍMITE CONOCIDO: la reserva vive unos minutos y vence sola. Si el organismo tardara
 * más que eso en contestar, el lugar se libera y otra emisión podría tomarlo, con lo
 * que ese mes terminaría con un comprobante más que el tope. Se acepta a propósito:
 * la alternativa (bloquear el contador durante toda la llamada al organismo) hace
 * esperar a quien factura, y una respuesta de ARCA que tarde más que el vencimiento
 * de la reserva es un caso raro. El recálculo lo detecta y lo informa.
 *
 * Esa reserva es la que evita el caso peor: dos comprobantes emitidos con un solo
 * lugar libre. Y vence sola si el proceso se corta a mitad de camino.
 */
final class PlanGate
{
    public function __construct(
        private readonly PlanSwitches $switches,
        private readonly EffectivePlanResolver $resolver,
        private readonly PlanUsage $usage,
        private readonly PlanCatalog $catalog,
    ) {
    }

    /**
     * ¿El corte está encendido para este contribuyente?
     *
     * Apagado (el default) significa que la emisión sigue exactamente como antes.
     */
    public function isEnabled(int $companyId): bool
    {
        return $this->switches->isEnabledFor($companyId);
    }

    public function plan(int $companyId): EffectivePlan
    {
        return $this->resolver->resolve($companyId);
    }

    /**
     * Qué recurso consume un comprobante, o null si no pasa por el corte.
     *
     * Códigos de ARCA: 1/6/11 son las facturas A/B/C y 201/206/211 las facturas de
     * crédito MiPyME; 2/7/12 son notas de débito y 3/8/13 notas de crédito.
     *
     * Las NOTAS nunca pasan por el corte: son la única salida para corregir un
     * comprobante mal emitido, así que cortarlas dejaría al contribuyente sin forma
     * de anular un error hasta el mes siguiente. Un código que no reconocemos cuenta
     * como factura: ante la duda, se cuenta (nunca emitir sin contar).
     */
    public function resourceForVoucherCode(int|string|null $cbteTipo): ?string
    {
        $codigo = (int) $cbteTipo;

        if (in_array($codigo, [2, 3, 7, 8, 12, 13], true)) {
            return null;
        }

        return PlanCatalog::RESOURCE_INVOICES;
    }

    /**
     * Toma un lugar para una emisión. Devuelve null cuando no hay corte que aplicar
     * (interruptor apagado o plan sin tope): en ese caso no hay nada que confirmar
     * después.
     *
     * @throws PlanLimitReachedException        tope agotado
     * @throws PlanDuplicateEmissionException   el mismo comprobante ya se emitió
     * @throws PlanUsageUnavailableException    no se pudo registrar el consumo
     */
    public function reserve(int $companyId, string $resource, string $keyHash): ?PlanUsageReservation
    {
        if (! $this->isEnabled($companyId)) {
            return null;
        }

        $plan = $this->resolver->resolve($companyId);
        $limit = $plan->limitFor($resource);

        // Sin tope no hay corte: se sigue sin reservar nada.
        if ($limit === null) {
            return null;
        }

        $period = PlanPeriod::current();
        $ttl = $this->switches->reservationMinutes();

        try {
            return DB::transaction(function () use ($companyId, $resource, $keyHash, $period, $ttl, $limit, $plan): PlanUsageReservation {
                // El bloqueo va primero: es lo que impide que dos pedidos
                // simultáneos cuenten el mismo lugar libre.
                $this->usage->lockCounter($companyId, $resource, $period);

                $reservation = $this->usage->reserve($companyId, $resource, $period, $keyHash, $ttl);

                if ($reservation->status === PlanUsageReservation::STATUS_CONFIRMED) {
                    throw new PlanDuplicateEmissionException($resource);
                }

                $used = $this->usage->used($companyId, $resource, $period);
                $held = $this->usage->held($companyId, $resource, $period);

                if ($used + $held > $limit) {
                    // No hace falta liberar la reserva: la excepción hace rollback de
                    // toda la transacción y el lugar recién tomado desaparece con
                    // ella. Liberarla antes sería un UPDATE que se deshace igual.
                    throw new PlanLimitReachedException(
                        resource: $resource,
                        used: $used,
                        limit: $limit,
                        period: $period->toArray(),
                        currentPlan: ['code' => $plan->code, 'name' => $plan->name],
                        suggestedPlan: $this->suggestedPlan($plan, $resource, $limit),
                    );
                }

                return $reservation;
            });
        } catch (PlanLimitReachedException | PlanDuplicateEmissionException $e) {
            throw $e;
        } catch (Throwable $e) {
            // El consumo no se pudo registrar: la emisión falla. Emitir sin contar
            // convertiría el contador en algo decorativo.
            throw new PlanUsageUnavailableException($resource, $e->getMessage());
        }
    }

    /**
     * El comprobante ya existe: el lugar pasa a consumo.
     */
    public function confirm(?PlanUsageReservation $reservation, ?int $documentId = null): void
    {
        if ($reservation === null) {
            return;
        }

        $this->usage->confirm($reservation, $documentId);
    }

    /**
     * La emisión no terminó en comprobante: el lugar vuelve a estar libre.
     *
     * Es seguro llamarlo siempre: si la reserva ya está confirmada, no hace nada.
     */
    public function release(?PlanUsageReservation $reservation): void
    {
        if ($reservation === null) {
            return;
        }

        $this->usage->release($reservation);
    }

    /**
     * Consumo sin paso por un organismo externo (remitos): se toma el lugar y se
     * confirma en el acto, pero por el mismo camino, así respeta el tope y la
     * idempotencia igual que una factura.
     *
     * @throws PlanLimitReachedException
     */
    public function consume(int $companyId, string $resource, string $keyHash, ?int $documentId = null): void
    {
        $reservation = $this->reserve($companyId, $resource, $keyHash);

        $this->confirm($reservation, $documentId);
    }

    /**
     * El estado del contador de un recurso, para la pantalla.
     *
     * @return array{used: int, limit: int|null, remaining: int|null, unlimited: bool, held: int}
     */
    public function usageOf(int $companyId, string $resource): array
    {
        $plan = $this->resolver->resolve($companyId);

        return $this->usage->snapshot($companyId, $resource, PlanPeriod::current(), $plan->limitFor($resource));
    }

    /**
     * Clave de idempotencia de una emisión.
     *
     * Se arma con lo que identifica al comprobante (empresa, punto de venta, tipo,
     * fecha, importe y cliente) y se guarda hasheada: el reintento del mismo pedido
     * cae en la misma clave y no consume dos veces.
     *
     * @param  array<string, mixed>  $identidad
     */
    public function keyFor(array $identidad): string
    {
        ksort($identidad);

        return hash('sha256', json_encode($identidad, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    /**
     * El plan más chico que resuelve el tope agotado.
     *
     * Es lo que la pantalla ofrece como "subí a este plan". Se calcula sobre el tope
     * del MISMO recurso: ofrecer un plan que no destraba nada sería peor que no
     * ofrecer ninguno.
     *
     * @return array{code: string, name: string, limit: int}|null
     */
    private function suggestedPlan(EffectivePlan $current, string $resource, int $limit): ?array
    {
        $candidato = null;

        foreach ($this->catalog->sellable() as $plan) {
            if ((int) $plan->id === (int) $current->planId) {
                continue;
            }

            $suyo = $plan->limitFor($resource);

            if ($suyo === null) {
                // Un plan sin tope para este recurso destraba siempre: no hay
                // candidato mejor, se corta acá.
                return ['code' => (string) $plan->code, 'name' => (string) $plan->name, 'limit' => 0];
            }

            if ($suyo > $limit && ($candidato === null || $suyo < $candidato['limit'])) {
                $candidato = ['code' => (string) $plan->code, 'name' => (string) $plan->name, 'limit' => (int) $suyo];
            }
        }

        return $candidato;
    }
}
