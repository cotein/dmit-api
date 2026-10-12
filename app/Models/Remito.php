<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un remito: el documento que ampara el traslado y la entrega de los bienes.
 *
 * La tabla existe desde 2023 y nunca se usó. Lo que la hace un documento y no
 * un borrador es `estado`: mientras está en 'borrador' se puede editar y no
 * tiene número; al emitirlo se le asigna el número del punto de venta, se
 * congela el `snapshot` y pasa a 'emitido'. Un remito emitido no se edita ni se
 * borra: se anula (el Anexo V, inciso V, punto 11.b exige conservar el
 * documento inutilizado con la leyenda "ANULADO" debidamente archivado).
 *
 * Deliberadamente FUERA de $fillable:
 *   - number: lo asigna el servicio al emitir, consumiendo la numeración del
 *     punto de venta. Si estuviera acá, un request podría inyectar un número
 *     que nunca se emitió (mismo criterio que `numero_cot` en el modelo Cot).
 *   - estado, emitido_at, anulado_at, anulado_motivo: son transiciones de
 *     estado, no datos que el cliente elige.
 *   - snapshot: se genera al emitir; nunca lo manda el front.
 *   - code: campo legado de la tabla original.
 */
class Remito extends Model
{
    use HasFactory;

    protected $table = 'remitos';

    protected $fillable = [
        'company_id',
        'customer_id',
        'pedido_cliente_id',
        'pto_vta',
        'clase',
        'delivery_date',
        'direccion_entrega',
        'commercial_reference',
        'payment_type_id',
        'sales_condition_id',
        'status_id',
        'user_id',
        'total',
        'transportista_nombre',
        'transportista_cuit',
        'transportista_domicilio',
        'observaciones',
    ];

    protected $casts = [
        'emitido_at' => 'datetime',
        'anulado_at' => 'datetime',
        // El snapshot es el documento congelado: se lee y se escribe como array.
        'snapshot' => 'array',
        'total' => 'float',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RemitoItem::class, 'remito_id', 'id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    /**
     * Condición de venta (contado, cuenta corriente a 30 días, etc.).
     *
     * Es un dato distinto del modo de pago: comparte el catálogo con la factura
     * (`sale_conditions`) pero se guarda en el remito porque el remito se emite
     * ANTES de facturar y es el papel que firma quien recibe la mercadería.
     */
    public function saleCondition(): HasOne
    {
        return $this->hasOne(SaleCondition::class, 'id', 'sales_condition_id');
    }

    /**
     * Facturas que cubren total o parcialmente este remito.
     *
     * Es de varios a varios: un remito puede facturarse en varias facturas y
     * una factura puede cubrir varios remitos. `importe` en el pivote dice
     * cuánto de este remito se facturó en cada una.
     */
    public function saleInvoices(): BelongsToMany
    {
        return $this->belongsToMany(SaleInvoices::class, 'remito_sale_invoice', 'remito_id', 'sale_invoice_id')
            ->withPivot('importe')
            ->withTimestamps();
    }

    public function scopeEmitidos($query)
    {
        return $query->where('estado', 'emitido');
    }

    public function scopeBorradores($query)
    {
        return $query->where('estado', 'borrador');
    }

    public function esEditable(): bool
    {
        return $this->estado === 'borrador';
    }

    /** Cuánto de este remito ya se facturó, sumando todas las facturas. */
    public function importeFacturado(): float
    {
        return (float) $this->saleInvoices->sum('pivot.importe');
    }

    /** Cuánto falta facturar. Nunca negativo, por si el remito se facturó con otro precio. */
    public function importePendiente(): float
    {
        return max(0, round((float) $this->total - $this->importeFacturado(), 2));
    }
}
