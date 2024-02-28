<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleInvoices extends Model
{
    use HasFactory;

    protected $table = 'sale_invoices';

    public function items(): HasMany
    {
        return $this->hasMany(SaleInvoicesItem::class, 'sale_invoice_id', 'id');
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class, 'id', 'company_id');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class, 'id', 'customer_id');
    }

    public function voucher(): HasOne
    {
        return $this->hasOne(AfipVoucher::class, 'id', 'voucher_id');
    }

    public function saleCondition(): HasOne
    {
        return $this->hasOne(SaleCondition::class, 'id', 'sales_condition_id');
    }
    public function comments(): HasOne
    {
        return $this->hasOne(SaleInvoicesComments::class, 'sale_invoice_id', 'id');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(SaleInvoices::class, 'id', 'parent_id');
    }
}
