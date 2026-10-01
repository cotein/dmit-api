<?php

namespace App\Src\Traits;

use App\Models\Company;
use App\Src\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompanyTrait
{
    protected static function bootBelongsToCompany()
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (session()->has('company_id')) {
                $model->company_id = session('company_id');
            } elseif (auth()->check()) {
                $company = auth()->user()->companies()->first();
                $model->company_id = $company ? $company->id : null;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}