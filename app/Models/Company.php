<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $casts = [
        'afip_data' => 'array'
    ];

    function users(): BelongsToMany
    {

        return $this->belongsToMany(User::class);
    }

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    public function afipInscription(): HasOne
    {
        return $this->hasOne(AfipInscription::class, 'id', 'afip_inscription_id');
    }

    public function afipDocument(): HasOne
    {
        return $this->hasOne(AfipDocument::class, 'id', 'afip_document_id');
    }

    public function afip_vouchers(): HasMany
    {
        return $this->hasMany(AfipVoucher::class, 'inscription_id', 'afip_inscription_id');
    }
}
