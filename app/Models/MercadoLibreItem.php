<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Espejo local de una publicación de MercadoLibre.
 *
 * No es el producto del catálogo: es la publicación. El producto local se
 * vincula por `product_id` (match por SKU o conciliación manual) y puede no
 * existir, que es el caso normal la primera vez que se importa.
 */
class MercadoLibreItem extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    /** Estados de vínculo con el catálogo local. */
    public const MATCH_MATCHED = 'matched';
    public const MATCH_UNMATCHED = 'unmatched';
    public const MATCH_IGNORED = 'ignored';

    protected $table = 'mercadolibre_items';

    protected $fillable = [
        'company_id',
        'mercadolibre_account_id',
        'item_id',
        'site_id',
        'title',
        'category_id',
        'domain_id',
        'listing_type_id',
        'condition',
        'currency_id',
        'price',
        'base_price',
        'available_quantity',
        'sold_quantity',
        'status',
        'sub_status',
        'permalink',
        'thumbnail',
        'catalog_listing',
        'catalog_product_id',
        'family_name',
        'user_product_id',
        'health',
        'tags',
        'product_id',
        'match_status',
        'matched_by',
        'matched_at',
        'ml_last_updated',
        'synced_at',
        'last_pushed_at',
        'last_push_error',
        'raw',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'catalog_listing' => 'boolean',
        'tags' => 'array',
        'raw' => 'array',
        'ml_last_updated' => 'datetime',
        'synced_at' => 'datetime',
        'matched_at' => 'datetime',
        'last_pushed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MercadoLibreAccount::class, 'mercadolibre_account_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(MercadoLibreVariation::class, 'mercadolibre_item_id');
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->where('match_status', self::MATCH_UNMATCHED);
    }

    public function scopeMatched(Builder $query): Builder
    {
        return $query->where('match_status', self::MATCH_MATCHED);
    }

    /** ¿Está publicada y visible? */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** El stock real de la publicación es la suma de sus variaciones. */
    public function hasVariations(): bool
    {
        return $this->variations()->exists();
    }
}
