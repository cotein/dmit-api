<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\AfipVoucher;
use App\Models\PlanUsageCounter;
use App\Models\PlanUsageReservation;
use App\Models\Remito;
use App\Models\SaleInvoices;
use App\Src\Remitos\RemitoService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * El contador del mes: cuánto se emitió, cuánto está en vuelo y cómo se descuenta.
 *
 * LAS TRES REGLAS QUE GOBIERNAN ESTA CLASE:
 *
 *  1. El consumo se descuenta al CONFIRMAR el comprobante, nunca al abrir el
 *     formulario ni al guardar un borrador. Si el organismo rechaza, no se consume
 *     nada.
 *
 *  2. Un comprobante consume UNA sola vez. El descuento es idempotente por la clave
 *     de la reserva: un reintento del mismo pedido cae en la misma fila y no suma.
 *
 *  3. Si el consumo no se puede registrar, la emisión FALLA. Lo que no se puede
 *     hacer es emitir sin contar: eso convertiría el contador en algo decorativo.
 *
 * El contador guarda lo CONFIRMADO. Las reservas vivas no se suman acá: para
 * decidir si hay lugar se miran las dos cosas (`availableFor`), porque una reserva
 * en vuelo ya ocupó su lugar aunque todavía no sea un comprobante.
 */
final class PlanUsage
{
    /**
     * Cuánto se emitió de verdad en el periodo.
     */
    public function used(int $companyId, string $resource, PlanPeriod $period): int
    {
        $row = PlanUsageCounter::query()
            ->where('company_id', $companyId)
            ->where('period', $period->key)
            ->where('resource', $resource)
            ->first();

        // Sin fila el consumo es CERO, no un error: es el caso normal de un mes que
        // todavía no tuvo emisiones.
        return $row === null ? 0 : (int) $row->used;
    }

    /**
     * Cuántos lugares están tomados por reservas vivas (emisiones en curso).
     */
    public function held(int $companyId, string $resource, PlanPeriod $period): int
    {
        return (int) PlanUsageReservation::query()
            ->where('company_id', $companyId)
            ->where('resource', $resource)
            ->where('period', $period->key)
            ->where('status', PlanUsageReservation::STATUS_RESERVED)
            ->where('expires_at', '>', now())
            ->count();
    }

    /**
     * Cuántos lugares quedan libres para emitir.
     *
     * Es lo que mira el gating, y suma la reserva viva: si no la sumara, dos
     * emisiones simultáneas con el tope a una entrarían las dos.
     */
    public function availableFor(int $companyId, string $resource, PlanPeriod $period, int $limit): int
    {
        return max(0, $limit - $this->used($companyId, $resource, $period) - $this->held($companyId, $resource, $period));
    }

    /**
     * Bloquea el renglón del contador de este CUIT, mes y recurso.
     *
     * POR QUÉ HACE FALTA: dos emisiones simultáneas del mismo contribuyente con el
     * tope justo en el límite podrían contar las dos el mismo lugar y entrar las dos,
     * porque cada transacción ve su propia reserva y no la de la otra. El bloqueo
     * serializa ese par de pedidos, y es un bloqueo CORTO: se toma, se reserva y se
     * suelta; la llamada al organismo pasa después, afuera, así que nadie espera a
     * ARCA para poder facturar.
     *
     * Se llama dentro de la transacción del gating. `used` y `held` que se lean
     * después de esto ya ven a los competidores.
     */
    public function lockCounter(int $companyId, string $resource, PlanPeriod $period): void
    {
        $counter = PlanUsageCounter::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'period' => $period->key,
                'resource' => $resource,
            ],
            ['used' => 0]
        );

        PlanUsageCounter::query()->whereKey($counter->id)->lockForUpdate()->first();
    }

    /**
     * El estado del contador para mostrar en pantalla.
     *
     * `held` viaja aparte y es informativo: la pantalla muestra el consumo real
     * (confirmado) y no se mueve por una emisión en curso. Sin tope, `limit` y
     * `remaining` van en NULL: la pantalla dice "sin límite" y nunca un número.
     *
     * @return array{used: int, limit: int|null, remaining: int|null, unlimited: bool, held: int}
     */
    public function snapshot(int $companyId, string $resource, PlanPeriod $period, ?int $limit): array
    {
        $used = $this->used($companyId, $resource, $period);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'unlimited' => $limit === null,
            'held' => $this->held($companyId, $resource, $period),
        ];
    }

    /**
     * Toma un lugar antes de llamar al organismo.
     *
     * Devuelve la reserva nueva, la misma reserva si el pedido es un reintento
     * (misma clave) o una reserva YA CONFIRMADA cuando ese comprobante ya se emitió:
     * en ese caso el gating no vuelve a llamar al organismo.
     */
    public function reserve(
        int $companyId,
        string $resource,
        PlanPeriod $period,
        string $keyHash,
        int $ttlMinutes
    ): PlanUsageReservation {        $existing = $this->findByKey($companyId, $resource, $keyHash);

        if ($existing !== null) {
            // Confirmada: el comprobante ya salió, no se vuelve a emitir ni a contar.
            if ($existing->status === PlanUsageReservation::STATUS_CONFIRMED) {
                return $existing;
            }

            // Viva: es el mismo intento reenviado, se reusa y se le renueva el plazo.
            if ($existing->isHolding()) {
                $existing->expires_at = now()->addMinutes($ttlMinutes);
                $existing->save();

                return $existing;
            }

            // Vencida o liberada: el lugar volvió hace rato, se recicla la fila para
            // no pelear con el índice único.
            $existing->status = PlanUsageReservation::STATUS_RESERVED;
            $existing->expires_at = now()->addMinutes($ttlMinutes);
            $existing->confirmed_at = null;
            $existing->invoice_id = null;
            $existing->save();

            return $existing;
        }

        try {
            return PlanUsageReservation::query()->create([
                'company_id' => $companyId,
                'period' => $period->key,
                'resource' => $resource,
                'key_hash' => $keyHash,
                'status' => PlanUsageReservation::STATUS_RESERVED,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        } catch (QueryException $e) {
            // Dos pedidos idénticos al mismo tiempo: el único hizo su trabajo. El
            // que perdió la carrera se queda con la fila del otro, que es
            // exactamente lo que la idempotencia quiere.
            $raced = $this->findByKey($companyId, $resource, $keyHash);

            if ($raced !== null) {
                return $raced;
            }

            throw $e;
        }
    }

    /**
     * Confirma el consumo: el comprobante ya existe y ahora sí cuenta.
     *
     * Todo dentro de una transacción con la fila bloqueada: si el descuento no se
     * puede escribir, la excepción sube y la emisión falla (nunca emitir sin
     * contar). Si la reserva ya estaba confirmada, no hace nada: es el reintento.
     */
    public function confirm(PlanUsageReservation $reservation, ?int $invoiceId = null): void
    {
        DB::transaction(function () use ($reservation, $invoiceId): void {
            $fresh = PlanUsageReservation::query()->whereKey($reservation->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->status === PlanUsageReservation::STATUS_CONFIRMED) {
                return;
            }

            $counter = PlanUsageCounter::query()->firstOrCreate(
                [
                    'company_id' => $fresh->company_id,
                    'period' => $fresh->period,
                    'resource' => $fresh->resource,
                ],
                ['used' => 0]
            );

            $locked = PlanUsageCounter::query()->whereKey($counter->id)->lockForUpdate()->first();
            $locked->used = (int) $locked->used + 1;
            $locked->save();

            $fresh->status = PlanUsageReservation::STATUS_CONFIRMED;
            $fresh->confirmed_at = now();
            $fresh->invoice_id = $invoiceId;
            $fresh->save();

            // El objeto que trajo quien llama tiene que quedar con el estado nuevo:
            // si no, el `release()` posterior del `finally` lo daría por reservado y
            // liberaría un consumo ya confirmado.
            $reservation->status = $fresh->status;
            $reservation->confirmed_at = $fresh->confirmed_at;
            $reservation->invoice_id = $fresh->invoice_id;
        });
    }

    /**
     * Libera el lugar cuando la emisión no terminó en comprobante (lo rechazó el
     * organismo, o se cortó el proceso antes de confirmar).
     */
    public function release(PlanUsageReservation $reservation, string $status = PlanUsageReservation::STATUS_RELEASED): void
    {
        if ($reservation->status === PlanUsageReservation::STATUS_CONFIRMED) {
            return;
        }

        $reservation->status = $status;
        $reservation->save();
    }

    /**
     * Libera las reservas vencidas: si el proceso se cortó entre la reserva y la
     * confirmación, el lugar tiene que volver solo, sin que nadie intervenga.
     *
     * @return int cuántas reservas se liberaron
     */
    public function sanitizeExpired(): int
    {
        return (int) PlanUsageReservation::query()
            ->where('status', PlanUsageReservation::STATUS_RESERVED)
            ->where('expires_at', '<=', now())
            ->update(['status' => PlanUsageReservation::STATUS_EXPIRED]);
    }

    /**
     * Recálculo contra los comprobantes del periodo.
     *
     * INFORMA, NO CORRIGE: devuelve la diferencia entre lo que dice el contador y
     * lo que dicen los comprobantes. Corregir en silencio taparía el bug que produjo
     * la diferencia, que es justamente lo que se quiere ver.
     *
     * @return array{resource: string, period: string, counter: int, documents: int, difference: int}
     */
    public function recount(int $companyId, string $resource, PlanPeriod $period): array
    {
        $counter = $this->used($companyId, $resource, $period);
        $documents = $resource === PlanCatalog::RESOURCE_REMITOS
            ? $this->countRemitos($companyId, $period)
            : $this->countInvoices($companyId, $period);

        return [
            'resource' => $resource,
            'period' => $period->key,
            'counter' => $counter,
            'documents' => $documents,
            'difference' => $documents - $counter,
        ];
    }

    /**
     * Facturas y notas de venta del periodo, SIN contar las notas de crédito y
     * débito, que nunca pasan por el corte.
     */
    private function countInvoices(int $companyId, PlanPeriod $period): int
    {
        return (int) SaleInvoices::query()
            ->where('company_id', $companyId)
            ->whereNotNull('cae')
            ->whereBetween('cbte_fch', [$period->from->format('Y-m-d'), $period->to->format('Y-m-d')])
            ->whereIn('voucher_id', $this->voucherIdsThatCount())
            ->count();
    }

    /**
     * IDs de los comprobantes que SÍ consumen tope: todo menos las notas de crédito
     * (3, 8, 13) y las de débito (2, 7, 12), que son la única salida para corregir
     * un comprobante mal emitido y por eso nunca se bloquean ni consumen.
     *
     * @return array<int>
     */
    private function voucherIdsThatCount(): array
    {
        $notas = ['2', '3', '7', '8', '12', '13'];

        return AfipVoucher::query()
            ->whereNotIn('afip_code', $notas)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Remitos emitidos en el periodo. El remito que ya existía y después se factura
     * no vuelve a consumir: acá sólo cuentan los EMITIDOS.
     */
    private function countRemitos(int $companyId, PlanPeriod $period): int
    {
        return (int) Remito::query()
            ->where('company_id', $companyId)
            ->where('estado', RemitoService::ESTADO_EMITIDO)
            ->whereBetween('emitido_at', [$period->from->startOfDay(), $period->to->endOfDay()])
            ->count();
    }

    private function findByKey(int $companyId, string $resource, string $keyHash): ?PlanUsageReservation
    {
        return PlanUsageReservation::query()
            ->where('company_id', $companyId)
            ->where('resource', $resource)
            ->where('key_hash', $keyHash)
            ->first();
    }
}
