<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleInvoicesItem extends Model
{
    use HasFactory;

    protected $table = 'sale_invoice_items';

    public function product(): HasOne
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    public function iva(): HasOne
    {
        return $this->hasOne(AfipIva::class, 'id', 'iva_id');
    }
}
