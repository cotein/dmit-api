<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * El periodo del contador: el mes CALENDARIO, no una ventana móvil.
 *
 * Se renueva el 1° del mes y lo no usado no se acumula: el tope es por mes, no un
 * saldo. Por eso el periodo es una clave ('2026-12') y no un rango de fechas que
 * cada consulta arme como quiera.
 *
 * Todo se resuelve en la zona horaria de la aplicación: si esto se calculara en UTC,
 * el último día del mes el contador se renovaría tres horas antes de la medianoche
 * argentina y alguien se quedaría sin poder facturar esa noche.
 */
final class PlanPeriod
{
    private function __construct(
        public readonly string $key,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {
    }

    public static function current(?CarbonInterface $now = null): self
    {
        $now = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now();

        return self::fromInstant($now);
    }

    /**
     * El periodo de una clave 'YYYY-MM' ya guardada (contadores y reservas viejas).
     *
     * Se construye desde la clave y no desde la fecha de hoy: el saneamiento y el
     * recálculo tienen que poder mirar un mes pasado sin que el resultado dependa
     * del día en que corran.
     */
    public static function fromKey(string $key): self
    {
        return self::fromInstant(CarbonImmutable::createFromFormat('Y-m-d', $key . '-01')->startOfDay());
    }

    private static function fromInstant(CarbonImmutable $instant): self
    {
        $start = $instant->startOfMonth();

        return new self(
            $start->format('Y-m'),
            $start,
            $start->endOfMonth()->startOfDay(),
        );
    }

    /**
     * Cuándo se renueva: el primer día del mes siguiente.
     *
     * Es lo que la pantalla muestra como "hasta cuándo vale" el contador.
     */
    public function renewsAt(): CarbonImmutable
    {
        return $this->from->addMonthNoOverflow()->startOfDay();
    }

    /**
     * @return array{from: string, to: string, renews_at: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->format('Y-m-d'),
            'to' => $this->to->format('Y-m-d'),
            'renews_at' => $this->renewsAt()->format('Y-m-d'),
        ];
    }
}
