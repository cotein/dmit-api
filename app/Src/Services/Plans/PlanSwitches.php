<?php

declare(strict_types=1);

namespace App\Src\Services\Plans;

use App\Models\PlanSetting;

/**
 * Los interruptores del corte de planes: configuración interna, nunca de pantalla.
 *
 * SON DOS, y por una razón concreta de puesta en marcha:
 *   - el GENERAL viene APAGADO (sin la fila en plan_settings no hay corte), así el
 *     día que esto se suba a producción nadie queda limitado por sorpresa: primero
 *     se carga el plan sin tope a todos los CUIT y recién después se enciende;
 *   - el POR CUIT permite encender el corte en un solo contribuyente de prueba y
 *     mirarlo de verdad antes de encenderlo para todos.
 *
 * No se cachea a propósito: son una lectura por clave única, y un interruptor que
 * tarda en apagarse es exactamente la vuelta atrás que no queremos que falle.
 */
final class PlanSwitches
{
    /** Minutos que vive una reserva sin confirmar antes de liberarse sola. */
    public const DEFAULT_RESERVATION_MINUTES = 5;

    /** Con cuántas restantes aparece el aviso suave. */
    public const DEFAULT_GRACE_REMAINING = 2;

    /**
     * ¿El corte está encendido para esta empresa?
     *
     * El interruptor por CUIT manda cuando está definido (encendido O apagado
     * explícito); si no, vale el general.
     */
    public function isEnabledFor(int $companyId): bool
    {
        $forCompany = $this->value(PlanSetting::gatingKeyForCompany($companyId));

        if ($forCompany !== null) {
            return $this->asBool($forCompany);
        }

        return $this->asBool($this->value(PlanSetting::GATING_ENABLED));
    }

    /**
     * Un CUIT apagado a mano queda apagado aunque el general esté encendido: es la
     * forma de sacar de la prueba a un contribuyente puntual sin apagar a todos.
     */
    public function isEnabledGlobally(): bool
    {
        return $this->asBool($this->value(PlanSetting::GATING_ENABLED));
    }

    public function setGlobally(bool $enabled): void
    {
        $this->put(
            PlanSetting::GATING_ENABLED,
            $enabled ? '1' : '0',
            'Interruptor general del corte por tope de plan. Apagado = todos facturan como antes.'
        );
    }

    /**
     * @param  bool|null  $enabled  null borra la excepción y vuelve a heredar del general.
     */
    public function setForCompany(int $companyId, ?bool $enabled): void
    {
        $key = PlanSetting::gatingKeyForCompany($companyId);

        if ($enabled === null) {
            PlanSetting::query()->where('key', $key)->delete();

            return;
        }

        $this->put($key, $enabled ? '1' : '0', 'Interruptor del corte por tope para el CUIT ' . $companyId . '.');
    }

    public function reservationMinutes(): int
    {
        $value = (int) $this->value(PlanSetting::RESERVATION_MINUTES);

        return $value > 0 ? $value : self::DEFAULT_RESERVATION_MINUTES;
    }

    public function graceRemaining(): int
    {
        $value = (int) $this->value(PlanSetting::GRACE_REMAINING);

        return $value > 0 ? $value : self::DEFAULT_GRACE_REMAINING;
    }

    /**
     * Estado completo de los interruptores, sólo para la configuración interna.
     *
     * @return array{global: bool, companies: array<int, bool>}
     */
    public function state(): array
    {
        $companies = [];

        foreach (PlanSetting::query()->where('key', 'like', PlanSetting::GATING_ENABLED_COMPANY_PREFIX . '%')->get() as $row) {
            $id = (int) substr($row->key, strlen(PlanSetting::GATING_ENABLED_COMPANY_PREFIX));
            $companies[$id] = $this->asBool($row->value);
        }

        return [
            'global' => $this->isEnabledGlobally(),
            'companies' => $companies,
        ];
    }

    private function value(string $key): ?string
    {
        $row = PlanSetting::query()->where('key', $key)->first();

        return $row?->value;
    }

    private function put(string $key, string $value, string $description): void
    {
        PlanSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'description' => $description]
        );
    }

    private function asBool(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'si', 'sí', 'on'], true);
    }
}
