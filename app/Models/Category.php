<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OwenIt\Auditing\Contracts\Auditable;

class Category extends Model implements Auditable
{
    use HasFactory,  \OwenIt\Auditing\Auditable;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
}
