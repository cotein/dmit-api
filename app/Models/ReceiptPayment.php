<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptPayment extends Model
{
    use HasFactory;

    /**
     * El recibo al que pertenece el pago: de ahi salen el cliente, la empresa,
     * la imputacion sobre los comprobantes y, si corresponde, la anulacion.
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class, 'receipt_id', 'id');
    }

    public function paymentType()
    {
        return $this->hasOne(PaymentType::class, 'id', 'payment_type_id');
    }

    public function bank()
    {
        return $this->hasOne(Bank::class, 'id', 'bank_id');
    }
}
