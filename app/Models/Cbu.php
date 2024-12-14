<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cbu extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'bank_id',
        'cbu',
        'alias',
        'cta_cte',
    ];

    public function bank(): HasOne
    {
        return $this->hasOne(Bank::class,  'id', 'bank_id');
    }

    public function deposits()
    {
        return $this->hasMany(ReceiptPayment::class, 'cbu_id', 'id');
    }
}
