<?php

namespace App\AuditResolvers;

use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Contracts\Resolver;

class CompanyIdResolver implements Resolver
{
    public static function resolve(Auditable $auditable = null)
    {
        /* if(method_exists($auditable, 'tenant')) {
            return $auditable->tenant_id;
        } */
        if ($auditable->company_id) {
            return $auditable->company_id;
        }

        return null;
    }
}
