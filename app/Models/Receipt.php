<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Receipt extends Model
{
    use HasFactory;

    public function invoices()
    {
        return $this->belongsToMany(SaleInvoices::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class,  'id', 'customer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceiptPayment::class);
    }

    public function cuentaCorriente()
    {
        return $this->morphOne(CustomerCuentaCorriente::class, 'cuotaable');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            $company = Company::find($receipt->company_id);
            if ($company) {
                $receipt->number = $company->getNextReceiptNumber();
            }
        });
    }
}
