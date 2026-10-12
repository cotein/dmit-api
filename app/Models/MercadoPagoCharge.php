<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cobro generado por la app hacia un pagador (link, QR de mostrador, Point o
 * suscripción). El estado del cobro se deriva de lo que MP contesta por webhook.
 */
class MercadoPagoCharge extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    public const CHANNEL_LINK = 'link';
    public const CHANNEL_POS_QR = 'pos_qr';
    public const CHANNEL_POINT = 'point';
    public const CHANNEL_SUBSCRIPTION = 'subscription';

    public const CONCEPT_INVOICE = 'invoice';
    public const CONCEPT_CURRENT_ACCOUNT = 'current_account';
    public const CONCEPT_SALE = 'sale';
    public const CONCEPT_MANUAL = 'manual';
    public const CONCEPT_SUBSCRIPTION = 'subscription';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROCESS = 'in_process';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'company_id',
        'customer_id',
        'sale_invoice_id',
        'receipt_id',
        'created_by',
        'channel',
        'concept',
        'external_reference',
        'preference_id',
        'mp_order_id',
        'init_point',
        'sandbox_init_point',
        'amount',
        'currency',
        'title',
        'description',
        'status',
        'status_detail',
        'expires_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoices::class, 'sale_invoice_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MercadoPagoPayment::class, 'charge_id');
    }

    /** El link que se comparte por WhatsApp/mail. En producción siempre es init_point. */
    public function shareUrl(): ?string
    {
        return $this->init_point ?: $this->sandbox_init_point;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
