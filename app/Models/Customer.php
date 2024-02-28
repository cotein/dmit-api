<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Customer extends Model
{
    use HasFactory;

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function afipDocument(): HasOne
    {
        return $this->hasOne(AfipDocument::class, 'id', 'afip_document_id');
    }

    public function afipInscription(): HasOne
    {
        return $this->hasOne(AfipInscription::class, 'id', 'afip_inscription_id');
    }

    public function saleInvoices(): HasMany
    {
        return $this->hasMany(SaleInvoices::class, 'customer_id', 'id');
    }
}
