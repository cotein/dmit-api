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

    public function documents_cancelation(): HasMany
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

    public function saleInvoices()
    {
        return $this->belongsToMany(SaleInvoices::class, 'receipt_sale_invoices')
            ->withPivot(
                'percentage_payment',
                'import_payment',
                'percentage_paid_history',
                'import_paid_history'
            )
            ->withTimestamps();
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
