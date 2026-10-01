<?php

namespace App\Src\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (Auth::check()) {
            $company = Auth::user()->companies()->first();
            if ($company) {
                $builder->where($model->getTable() . '.company_id', $company->id);
            }
        }
    }
}