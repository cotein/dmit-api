<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago concreto devuelto por Mercado Pago. Un cobro puede tener varios intentos
 * (rechazado → aprobado), así que la UI muestra el cobro y esta tabla guarda la
 * historia. `raw` permite auditar el estado contra lo que MP contestó.
 */
class MercadoPagoPayment extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    protected $fillable = [
        'company_id',
        'charge_id',
        'mp_payment_id',
        'mp_merchant_order_id',
        'mp_preapproval_id',
        'status',
        'status_detail',
        'amount',
        'fee_amount',
        'net_amount',
        'payment_method_id',
        'payment_type',
        'installments',
        'payer_email',
        'payer_id',
        'external_reference',
        'raw',
        'approved_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'raw' => 'array',
        'approved_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function charge(): BelongsTo
    {
        return $this->belongsTo(MercadoPagoCharge::class, 'charge_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
