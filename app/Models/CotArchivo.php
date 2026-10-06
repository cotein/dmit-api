<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bitácora de un archivo TXT presentado a ARBA.
 *
 * El nombre del archivo es único: es la clave con la que ARBA deduplica
 * (error 11, "El archivo recibido ya fue procesado con anterioridad") y con la
 * que nosotros distinguimos un reenvío legítimo de un duplicado.
 *
 * Deliberadamente FUERA de $fillable: numero_comprobante, codigo_integridad,
 * respuesta, error_tipo, error_codigo, error_mensaje, http_status, estado y
 * hash. Los escribe el cliente de ARBA al enviar, no el usuario.
 */
class CotArchivo extends Model
{
    use HasFactory;

    protected $table = 'cot_archivos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_ENVIADO = 'enviado';

    public const ESTADO_PROCESADO = 'procesado';

    public const ESTADO_RECHAZADO = 'rechazado';

    public const ESTADO_ERROR = 'error';

    protected $fillable = [
        'company_id',
        'user_id',
        'company_traslado_point_id',
        'nombre_archivo',
        'cuit_empresa',
        'planta',
        'puerta',
        'fecha_archivo',
        'secuencia',
        'cantidad_remitos',
        'contenido',
    ];

    protected $casts = [
        'fecha_archivo' => 'date',
        'enviado_at' => 'datetime',
    ];

    public function cots(): HasMany
    {
        return $this->hasMany(Cot::class, 'cot_archivo_id', 'id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
}
