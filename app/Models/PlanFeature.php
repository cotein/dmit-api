<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una función habilitada dentro de un plan (logo, marca de agua, integraciones).
 *
 * La ausencia de la fila es "no la tiene". El corte de branding y el de
 * integraciones van a leer de acá para bloquear en pantalla Y en el servidor; en
 * este corte las filas ya se cargan, así que cuando esas piezas se escriban el
 * bloqueo tiene de dónde leer sin migrar nada.
 */
class PlanFeature extends Model
{
    public const FEATURE_LOGO = 'logo';
    public const FEATURE_WATERMARK = 'watermark';
    public const FEATURE_MERCADOPAGO = 'mercadopago';
    public const FEATURE_MERCADOLIBRE = 'mercadolibre';
    public const FEATURE_API = 'api';

    protected $fillable = [
        'plan_id',
        'feature',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
