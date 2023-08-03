<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $casts = [
        'afip_data' => 'array'
    ];

    function users(): HasMany
    {

        return $this->hasMany(User::class, 'company_id', 'id');
    }
}
