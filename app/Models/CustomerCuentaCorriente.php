<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerCuentaCorriente extends Model
{
    use HasFactory;

    public function cuotaable()
    {
        return $this->morphTo();
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer_cuenta_corriente) {
            $company = Company::find($customer_cuenta_corriente->company_id);
            if ($company) {
                $customer_cuenta_corriente->number = $company->getNextReceiptNumber();
            }
        });
    }
}
