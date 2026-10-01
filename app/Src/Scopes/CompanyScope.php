<?php

namespace App\Src\Scopes;

use App\Src\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra por la empresa activa del request.
 *
 * Fail-closed: si no se puede resolver una empresa (sin usuario autenticado, o
 * usuario sin empresas) no se devuelve ninguna fila. Antes no filtraba nada en
 * ese caso, así que cualquier endpoint sin 'auth:api' o cualquier job veía todas
 * las empresas.
 *
 * Para contextos explícitos (consola, seeders, comandos de migración de datos)
 * usar ->withoutGlobalScope(CompanyScope::class).
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(CurrentCompany::class)->id();

        if ($companyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->getTable().'.company_id', $companyId);
    }
}
