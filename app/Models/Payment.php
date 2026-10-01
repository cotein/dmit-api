<?php

namespace App\Models;

use App\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    protected $fillable = [
        'order_id',
        'payment_method_id',
        'amount',
        'payment_date',
        'status',
        'reference_number',
        'notes',
        'company_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}