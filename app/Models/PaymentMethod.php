<?php

namespace App\Models;

use App\Src\Traits\BelongsToCompanyTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory, BelongsToCompanyTrait;

    protected $fillable = ['name', 'company_id'];
}
