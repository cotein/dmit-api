<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Product extends Model implements HasMedia, Auditable
{
    use HasFactory, InteractsWithMedia, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'sub_title',
        'description',
        'iva_id',
        'money_id',
        'priority_id',
        'published_meli',
        'published_here',
        'slug',
        'critical_stock',
        'apply_discount',
        'apply_discount_amount',
        'apply_discount_percentage',
        'see_price_on_the_web',
    ];



    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function pricelist(): BelongsToMany
    {
        return $this->belongsToMany(PriceList::class, 'price_list_product', 'product_id', 'pricelist_id')->withPivot(['price', 'profit_percentage', 'profit_rate']);
    }

    public function iva(): HasOne
    {
        return $this->hasOne(AfipIva::class, 'id', 'iva_id');
    }

    public function stock_history(): HasMany
    {
        return $this->hasMany(Stock::class);
    }
}
