<?php

namespace App\Models;

use App\Src\Arba\Cot\CotFileName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un COT: el registro 02 del archivo TXT presentado a ARBA.
 *
 * Es una entidad propia y previa al traslado, no una acción sobre una factura.
 * sale_invoice_id referencia la documentación respaldatoria cuando existe; si
 * es nulo, el COT se cargó a mano.
 *
 * Deliberadamente FUERA de $fillable:
 *   numero_cot, procesado y errores.
 * Son el resultado que devuelve ARBA. Si estuvieran acá, un request podría
 * inyectar un número de COT que nunca se emitió.
 */
class Cot extends Model
{
    use HasFactory;

    protected $table = 'cots';

    protected $fillable = [
        'cot_archivo_id',
        'company_id',
        'sale_invoice_id',
        'sujeto_generador',
        'codigo_unico',
        'fecha_emision',
        'fecha_salida_transporte',
        'hora_salida_transporte',
        'destinatario_consumidor_final',
        'destinatario_tipo_documento',
        'destinatario_documento',
        'destinatario_cuit',
        'destinatario_razon_social',
        'destinatario_tenedor',
        'destino_domicilio_calle',
        'destino_domicilio_numero',
        'destino_domicilio_comple',
        'destino_domicilio_piso',
        'destino_domicilio_dto',
        'destino_domicilio_barrio',
        'destino_domicilio_codigo_postal',
        'destino_domicilio_localidad',
        'destino_domicilio_provincia',
        'propio_destino_domicilio_codigo',
        'entrega_domicilio_origen',
        'origen_cuit',
        'origen_razon_social',
        'emisor_tenedor',
        'origen_domicilio_calle',
        'origen_domicilio_numero',
        'origen_domicilio_comple',
        'origen_domicilio_piso',
        'origen_domicilio_dto',
        'origen_domicilio_barrio',
        'origen_domicilio_codigo_postal',
        'origen_domicilio_localidad',
        'origen_domicilio_provincia',
        'transportista_cuit',
        'tipo_recorrido',
        'recorrido_localidad',
        'recorrido_calle',
        'recorrido_ruta',
        'patente_vehiculo',
        'patente_acoplado',
        'producto_no_term_dev',
        'importe',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_salida_transporte' => 'date',
        'importe' => 'decimal:2',
        'destinatario_consumidor_final' => 'boolean',
        'destinatario_tenedor' => 'boolean',
        'entrega_domicilio_origen' => 'boolean',
        'emisor_tenedor' => 'boolean',
        'producto_no_term_dev' => 'boolean',
        'procesado' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(CotItem::class, 'cot_id', 'id');
    }

    /**
     * Las dos fechas del COT circulan en formato AAAAMMDD, que es el del archivo
     * de ARBA, y la columna es DATE.
     *
     * Sin estos mutadores hay un bug silencioso: Eloquent recibe la cadena
     * numérica '20261005' y, como is_numeric() da true, la interpreta como un
     * timestamp de Unix. El remito termina guardado el 23 de agosto de 1970 y
     * nadie se entera hasta que ARBA lo rechaza por fecha (error 22).
     *
     * Se acepta AAAAMMDD, una fecha ISO, un d/m/Y o un objeto de fecha, y
     * siempre se guarda en el formato que espera la base.
     */
    public function setFechaEmisionAttribute($value): void
    {
        $this->attributes['fecha_emision'] = self::fechaParaLaBase($value);
    }

    public function setFechaSalidaTransporteAttribute($value): void
    {
        $this->attributes['fecha_salida_transporte'] = self::fechaParaLaBase($value);
    }

    private static function fechaParaLaBase($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $aaaammdd = CotFileName::normalizeFecha($value);

        return substr($aaaammdd, 0, 4) . '-' . substr($aaaammdd, 4, 2) . '-' . substr($aaaammdd, 6, 2);
    }

    public function archivo(): BelongsTo
    {
        return $this->belongsTo(CotArchivo::class, 'cot_archivo_id', 'id');
    }

    /**
     * Documentación respaldatoria. Puede ser nula: el COT también se puede
     * cargar a mano.
     */
    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoices::class, 'sale_invoice_id', 'id');
    }

    /**
     * El COT ya tiene número asignado por ARBA.
     */
    public function estaEmitido(): bool
    {
        return ! empty($this->numero_cot);
    }
}
