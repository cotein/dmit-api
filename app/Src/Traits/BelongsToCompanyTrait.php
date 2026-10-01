<?php

namespace App\Src\Traits;

use App\Models\Company;
use App\Src\Scopes\CompanyScope;
use App\Src\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompanyTrait
{
    /**
     * El nombre debe ser boot + el nombre completo del trait: Laravel busca
     * 'bootBelongsToCompanyTrait'. Con 'bootBelongsToCompany' el trait quedaba
     * inerte (ni global scope ni hook de creating).
     */
    protected static function bootBelongsToCompanyTrait(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $companyId = app(CurrentCompany::class)->id();

            // En un request autenticado la empresa manda el servidor, nunca el cliente.
            // Sin usuario (consola, seeders, factories) se respeta el valor explícito.
            if ($companyId !== null) {
                $model->company_id = $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
