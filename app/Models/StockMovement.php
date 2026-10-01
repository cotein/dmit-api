<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity',
        'stock_after_change',
        'type',
        'sourceable_id',
        'sourceable_type',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'stock_after_change' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
