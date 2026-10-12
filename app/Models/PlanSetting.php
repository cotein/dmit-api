<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuración interna del corte de planes: clave y valor.
 *
 * Son llaves de operación (interruptor del gating, minutos de vida de una reserva)
 * y NUNCA se exponen en la pantalla del cliente.
 */
class PlanSetting extends Model
{
    /** Interruptor general del gating. Sin la fila, el corte está APAGADO. */
    public const GATING_ENABLED = 'gating.enabled';

    /** Prefijo del interruptor por CUIT: 'gating.enabled.company.{id}'. */
    public const GATING_ENABLED_COMPANY_PREFIX = 'gating.enabled.company.';

    /** Cuántos minutos vive una reserva sin confirmar. */
    public const RESERVATION_MINUTES = 'usage.reservation_minutes';

    /** Con cuántas restantes aparece el aviso suave (por defecto 2). */
    public const GRACE_REMAINING = 'usage.grace_remaining';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    /**
     * El interruptor por CUIT, con el nombre que usa la configuración interna.
     */
    public static function gatingKeyForCompany(int $companyId): string
    {
        return self::GATING_ENABLED_COMPANY_PREFIX . $companyId;
    }
}
