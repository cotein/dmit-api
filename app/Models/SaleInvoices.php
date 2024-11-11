<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Src\Constantes as Cosntantes;
use App\Src\Constantes;

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

    public function children(): HasMany
    {
        return $this->hasMany(SaleInvoices::class, 'parent_id', 'id');
    }

    public function parents(): HasMany
    {
        return $this->hasMany(SaleInvoices::class, 'id', 'parent_id');
    }

    public function paymentType(): HasOne
    {
        return $this->hasOne(PaymentType::class, 'id', 'payment_type_id');
    }

    public function receipts()
    {
        return $this->belongsToMany(Receipt::class, 'receipt_sale_invoices')
            ->withPivot(
                'percentage_payment',
                'import_payment',
                'percentage_paid_history',
                'import_paid_history'
            )
            ->withTimestamps();
    }

    public function getPreviousPayments()
    {
        $totalPreviousPayments = 0;

        foreach ($this->receipts as $receipt) {
            $totalPreviousPayments += $receipt->pivot->import_payment;
        }

        return $totalPreviousPayments;
    }

    public function cuentaCorriente()
    {
        return $this->morphOne(CustomerCuentaCorriente::class, 'cuotaable');
    }

    public function totalInvoiced()
    {
        return $this->items->sum('neto_import') + $this->items->sum('iva_import') + $this->items->sum('percep_iibb_import') + $this->items->sum('percep_iva_import');
    }

    public function isNotaCredito()
    {
        return in_array($this->voucher_id, Cosntantes::IS_NOTA_CREDITO);
    }
}
